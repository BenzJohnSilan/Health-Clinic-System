<?php

namespace App\Services;

use App\Exceptions\RestoreException;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\BackupFilesystem;
use Throwable;

/**
 * Reads a mysqldump file (the one BackupService put in the archive) back into
 * the application's own database connection.
 *
 * Why not just pipe the file into the `mysql` program?
 *  - no external program to find on shared hosting, and no password ever
 *    written to a file or a command line — it uses Laravel's connection;
 *  - every statement can be checked BEFORE anything is executed.
 *
 * Two passes over the same file:
 *   scan()    reads everything and checks every statement against an
 *             allowlist. Executes NOTHING. Anything unexpected rejects the
 *             whole dump while the live database is still untouched.
 *   import()  runs scan() again, then executes exactly the statements that
 *             passed — nothing else.
 *
 * What a dump may contain (that is all mysqldump writes for plain tables):
 *   DROP TABLE IF EXISTS `t`;  CREATE TABLE `t` ( ... ) ENGINE=...;
 *   INSERT INTO `t` VALUES (...),(...);      SET ... session settings
 *   LOCK/UNLOCK TABLES and ALTER TABLE .. KEYS   (recognised, skipped)
 * Views, triggers, routines, events, GRANT, USE, CREATE/DROP DATABASE, LOAD
 * DATA, multiple statements on one line ... are all REJECTED.
 *
 * Tables in $preserve keep their live contents: their statements are read and
 * checked like any other but never executed.
 *
 * Limit, stated honestly: this stops corrupted/unexpected dumps and any
 * statement outside the list above. It is not a substitute for keeping
 * backups private — a backup is trusted as much as the folder it lives in.
 */
class BackupDatabaseImporter
{
    /** Session variables a dump is allowed to set (lower-case). */
    private const SET_VARIABLES = [
        'character_set_client', 'character_set_results', 'character_set_connection', 'collation_connection',
        'time_zone', 'unique_checks', 'foreign_key_checks', 'sql_mode', 'sql_notes', 'note_verbosity',
        '@saved_cs_client', '@old_character_set_client', '@old_character_set_results', '@old_collation_connection',
        '@old_time_zone', '@old_unique_checks', '@old_foreign_key_checks', '@old_sql_mode', '@old_sql_notes',
        '@old_note_verbosity',
    ];

    /**
     * Pass 1: check the whole dump, execute nothing.
     *
     * @param  array<int,string>  $preserve  table names to leave untouched
     * @return array{tables:array<int,string>, restorable:array<int,string>, statements:int, executable:int}
     */
    public function scan(string $dumpPath, array $preserve = []): array
    {
        $preserveSet = $this->normalize($preserve);

        $this->assertComplete($dumpPath);

        $created    = [];
        $statements = 0;
        $executable = 0;

        foreach ($this->statements($dumpPath, $preserveSet) as $statement) {
            $statements++;

            if ($statement['kind'] === 'create') {
                $created[$statement['table']] = true;
            }
            if (!$statement['skip']) {
                $executable++;
            }
        }

        if ($created === []) {
            throw new RestoreException('The backup does not contain any database tables.');
        }

        $tables     = array_keys($created);
        $restorable = array_values(array_filter($tables, fn (string $t) => !isset($preserveSet[strtolower($t)])));

        return compact('tables', 'restorable', 'statements', 'executable');
    }

    /**
     * Confirms, BEFORE anything is replaced, that the database user is allowed
     * to create and drop tables (shared-hosting accounts sometimes are not).
     */
    public function preflight(): void
    {
        $probe = '__bk_restore_probe';

        try {
            DB::statement("DROP TABLE IF EXISTS `$probe`");
            DB::statement("CREATE TABLE `$probe` (id INT)");
            DB::statement("DROP TABLE `$probe`");
        } catch (Throwable $e) {
            throw new RestoreException('The database account is not allowed to replace tables, so nothing was restored.', 0, $e);
        }
    }

    /**
     * Pass 2: validate again, then run the approved statements.
     *
     * @param  array<int,string>  $preserve
     * @return array{tables:array<int,string>, restorable:array<int,string>, statements:int, executable:int, executed:int}
     */
    public function import(string $dumpPath, array $preserve = []): array
    {
        $info        = $this->scan($dumpPath, $preserve);
        $preserveSet = $this->normalize($preserve);
        $executed    = 0;
        $current     = null;

        // A fresh connection, so the dump's session settings (foreign key
        // checks off, UTC time zone ...) can never leak into the app's own.
        DB::purge();

        try {
            $pdo = DB::connection()->getPdo();

            foreach ($this->statements($dumpPath, $preserveSet) as $statement) {
                if ($statement['skip']) {
                    continue;
                }

                $current = $statement;
                $pdo->exec($statement['sql']);
                $executed++;
            }
        } catch (Throwable $e) {
            Log::error('Backup restore: the database import stopped.', [
                'statement' => $current['kind'] ?? null,
                'table'     => $current['table'] ?? null,
                'error'     => BackupFilesystem::scrub($e->getMessage()),
            ]);

            throw new RestoreException('The database could not be restored.', 0, $e);
        } finally {
            DB::purge();
        }

        return $info + ['executed' => $executed];
    }

    // ---------------------------------------------------------------------
    // Reading the dump
    // ---------------------------------------------------------------------

    /**
     * Yields every statement that matters:
     *   ['kind' => drop|create|insert|set, 'table' => ?string, 'sql' => string, 'skip' => bool]
     * Comments and recognised housekeeping lines are silently passed over;
     * anything else throws.
     *
     * @param  array<string,bool>  $preserveSet  lower-case table names
     */
    private function statements(string $path, array $preserveSet): Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RestoreException('The database dump could not be read.');
        }

        $created     = [];
        $inCreate    = false;
        $createTable = null;
        $inInsert    = false;
        $insertTable = null;
        $buffer      = '';
        $lineNo      = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNo++;
                $text = rtrim($line, "\r\n");

                // A CREATE TABLE spans several lines, up to the ") ENGINE=...;" line.
                if ($inCreate) {
                    $buffer .= "\n" . $text;

                    if (preg_match('/^\)[^;]*;$/', $text)) {
                        $inCreate = false;

                        if (!$this->isSingleStatement($buffer)) {
                            $this->reject($lineNo);
                        }

                        $created[strtolower($createTable)] = true;

                        yield ['kind' => 'create', 'table' => $createTable, 'sql' => $buffer, 'skip' => isset($preserveSet[strtolower($createTable)])];
                        $buffer = '';
                    }

                    continue;
                }

                // MariaDB writes "INSERT INTO `t` VALUES" and then one "(...)," line per row;
                // MySQL writes the whole statement on one line. Both end with ";".
                if ($inInsert) {
                    if (!str_starts_with($text, '(') || !preg_match('/[,;]$/', $text)) {
                        $this->reject($lineNo);
                    }

                    $buffer .= "\n" . $text;
                }

                if ($inInsert || (str_starts_with($text, 'INSERT INTO `')
                        && preg_match('/^INSERT INTO `([A-Za-z0-9_]+)`(?: \([^)]*\))? VALUES(?: \(|$)/', $text, $m))) {
                    if (!$inInsert) {
                        if (!isset($created[strtolower($m[1])])) {
                            $this->reject($lineNo);
                        }

                        $inInsert    = true;
                        $insertTable = $m[1];
                        $buffer      = $text;
                    }

                    if (strlen($buffer) > 134217728) {
                        $this->reject($lineNo); // a single statement over 128 MB is not a mysqldump statement
                    }

                    if (str_ends_with($text, ';')) {
                        $state = $this->statementState($buffer);

                        if ($state === 'invalid') {
                            $this->reject($lineNo);
                        }
                        if ($state === 'complete') {
                            $inInsert = false;

                            yield ['kind' => 'insert', 'table' => $insertTable, 'sql' => $buffer, 'skip' => isset($preserveSet[strtolower($insertTable)])];
                            $buffer = '';
                        }
                    }

                    continue;
                }

                if ($text === '' || str_starts_with($text, '--')) {
                    continue;
                }

                if (str_starts_with($text, '/*')) {
                    // MariaDB "sandbox mode" marker: a comment, never executed.
                    if (preg_match('#^/\*M?!999999\\\\?- enable the sandbox mode \*/\s*$#', $text)) {
                        continue;
                    }

                    // Conditional comments: /*!40101 SET ... */;  /*!40000 ALTER TABLE `t` DISABLE KEYS */;
                    if (preg_match('#^/\*M?!\d+\s+(.*?)\s*\*/;$#', $text, $m)) {
                        $inner = $m[1];

                        if (preg_match('/^SET\s/i', $inner)) {
                            yield ['kind' => 'set', 'table' => null, 'sql' => $inner, 'skip' => !$this->isAllowedSet($inner)];

                            continue;
                        }
                        if (preg_match('/^ALTER TABLE `[A-Za-z0-9_]+` (DISABLE|ENABLE) KEYS$/i', $inner)
                            || preg_match('/^SELECT COUNT\(\*\) INTO @rocksdb_/i', $inner)) {
                            continue; // housekeeping, not needed
                        }
                    }

                    $this->reject($lineNo); // e.g. views, triggers, routines in conditional comments
                }

                // Plain SET lines (e.g. GTID settings on some servers): only the
                // known-harmless ones are run, the rest are simply skipped.
                if (preg_match('/^SET\s/i', $text) && str_ends_with($text, ';')) {
                    $sql = rtrim($text, ';');

                    yield ['kind' => 'set', 'table' => null, 'sql' => $sql, 'skip' => !$this->isAllowedSet($sql)];

                    continue;
                }

                if (preg_match('/^DROP TABLE IF EXISTS `([A-Za-z0-9_]+)`;$/', $text, $m)) {
                    yield ['kind' => 'drop', 'table' => $m[1], 'sql' => $text, 'skip' => isset($preserveSet[strtolower($m[1])])];

                    continue;
                }

                if (preg_match('/^CREATE TABLE `([A-Za-z0-9_]+)` \($/', $text, $m)) {
                    $inCreate    = true;
                    $createTable = $m[1];
                    $buffer      = $text;

                    continue;
                }

                if (preg_match('/^LOCK TABLES `[A-Za-z0-9_]+` WRITE;$/', $text) || $text === 'UNLOCK TABLES;') {
                    continue; // not needed for a single-connection import
                }

                $this->reject($lineNo);
            }

            if ($inCreate || $inInsert) {
                throw new RestoreException('The database dump is incomplete.');
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * mysqldump ends a finished dump with "-- Dump completed on ...".
     */
    private function assertComplete(string $path): void
    {
        if (!is_file($path) || filesize($path) === 0) {
            throw new RestoreException('The database dump is missing or empty.');
        }

        $size   = filesize($path);
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - 4096));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        if (!str_contains($tail, 'Dump completed')) {
            throw new RestoreException('The database dump is incomplete.');
        }
    }

    private function reject(int $lineNo): never
    {
        Log::warning('Backup restore: the dump has a statement restore does not accept.', ['line' => $lineNo]);

        throw new RestoreException('The backup contains database content that restore does not accept, so nothing was restored.');
    }

    /**
     * SET statements that are safe to run, and only those.
     */
    private function isAllowedSet(string $statement): bool
    {
        $vars   = implode('|', array_map(fn (string $v) => preg_quote($v, '/'), self::SET_VARIABLES));
        $value  = '(?:@@?[A-Za-z_]+|\'[^\'\\\\;]*\'|-?\d+|[A-Za-z0-9_]+)';
        $assign = '(?:(?:' . $vars . ')\s*=\s*' . $value . ')';

        return preg_match('/^SET\s+(?:NAMES\s+[A-Za-z0-9_]+|' . $assign . '(?:\s*,\s*' . $assign . ')*)$/i', $statement) === 1;
    }

    /**
     * True only if $sql is exactly ONE finished statement.
     */
    private function isSingleStatement(string $sql): bool
    {
        return $this->statementState($sql) === 'complete';
    }

    /**
     * Where a statement stands, judged outside quotes only:
     *   complete  its only semicolon is the very last character
     *   open      no closing semicolon yet, or a quote is still open (more lines to come)
     *   invalid   a semicolon appears before the end: a SECOND statement is hiding in it
     *             ("INSERT ...; DROP TABLE users;")
     */
    private function statementState(string $sql): string
    {
        $length = strlen($sql);
        $i      = 0;

        while ($i < $length) {
            $i += strcspn($sql, "'\"`;", $i);

            if ($i >= $length) {
                return 'open';
            }

            $char = $sql[$i];

            if ($char === ';') {
                return $i === $length - 1 ? 'complete' : 'invalid';
            }

            // A quoted string / identifier: skip to its closing quote.
            $quote = $char;
            $i++;

            while (true) {
                $i += strcspn($sql, $quote === '`' ? '`' : '\\' . $quote, $i);

                if ($i >= $length) {
                    return 'open'; // quote not closed yet
                }
                if ($sql[$i] === '\\') {
                    $i += 2;        // escaped character

                    continue;
                }
                if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                    $i += 2;        // doubled quote = literal quote

                    continue;
                }

                $i++;

                break;
            }
        }

        return 'open';
    }

    /**
     * @param  array<int,string>  $tables
     * @return array<string,bool> lower-case name => true
     */
    private function normalize(array $tables): array
    {
        $set = [];

        foreach ($tables as $table) {
            $set[strtolower((string) $table)] = true;
        }

        return $set;
    }
}
