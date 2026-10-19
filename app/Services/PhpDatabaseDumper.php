<?php

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Writes a mysqldump-style SQL file using PHP only (PDO).
 *
 * Why this exists: some shared hosts (Hostinger) switch off proc_open, so the
 * real `mysqldump` program cannot be started from PHP. This class produces the
 * same kind of file in the same layout, so the backup validation and the
 * restore importer (BackupDatabaseImporter) accept it without any change:
 *
 *   -- comments
 *   /*!40101 SET NAMES utf8mb4 *\/;
 *   /*!40014 SET FOREIGN_KEY_CHECKS=0 *\/;
 *   DROP TABLE IF EXISTS `t`;
 *   CREATE TABLE `t` ( ... ) ENGINE=...;        (taken from SHOW CREATE TABLE)
 *   INSERT INTO `t` VALUES (...),(...);         (one single line per statement)
 *   /*!40014 SET FOREIGN_KEY_CHECKS=1 *\/;
 *   -- Dump completed on YYYY-MM-DD HH:MM:SS
 *
 * Only base tables are dumped (same as the table list the backup verifies).
 * Read-only: it runs inside a consistent-snapshot transaction, so the data in
 * the file is one point in time and nothing is ever locked or changed.
 */
class PhpDatabaseDumper
{
    /** Rows per INSERT statement, and a size cap so one line never gets huge. */
    private const ROWS_PER_INSERT = 200;

    private const MAX_INSERT_BYTES = 1048576; // 1 MB

    /**
     * @param  array<int,string>  $tables  base-table names to dump (in this order)
     * @return int number of tables written
     */
    public function dump(PDO $pdo, string $database, array $tables, string $targetPath): int
    {
        $handle = @fopen($targetPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Could not create the database dump file.');
        }

        $snapshot = false;

        try {
            // One consistent point in time, like mysqldump --single-transaction.
            try {
                $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
                $snapshot = true;
            } catch (\Throwable) {
                $snapshot = false; // not fatal: dump still works, just not a single snapshot
            }

            $this->write($handle, "-- ClinicRMS database dump (PHP)\n");
            $this->write($handle, '-- Database: ' . str_replace(["\r", "\n"], ' ', $database) . "\n");
            $this->write($handle, '-- Started on ' . date('Y-m-d H:i:s') . "\n\n");
            $this->write($handle, "/*!40101 SET NAMES utf8mb4 */;\n");
            $this->write($handle, "/*!40014 SET FOREIGN_KEY_CHECKS=0 */;\n\n");

            $count = 0;

            foreach ($tables as $table) {
                $this->dumpTable($pdo, $handle, (string) $table);
                $count++;
            }

            $this->write($handle, "/*!40014 SET FOREIGN_KEY_CHECKS=1 */;\n\n");
            $this->write($handle, '-- Dump completed on ' . date('Y-m-d H:i:s') . "\n");
        } finally {
            if ($snapshot) {
                try {
                    $pdo->exec('COMMIT');
                } catch (\Throwable) {
                    // read-only snapshot: nothing to lose
                }
            }

            fclose($handle);
        }

        return $count;
    }

    /**
     * @param  resource  $handle
     */
    private function dumpTable(PDO $pdo, $handle, string $table): void
    {
        $this->assertSafeName($table);

        $quoted = '`' . $table . '`';
        $create = $this->createStatement($pdo, $table);

        $this->write($handle, "--\n-- Table structure for table {$quoted}\n--\n\n");
        $this->write($handle, "DROP TABLE IF EXISTS {$quoted};\n");
        $this->write($handle, $create . ";\n\n");

        $binary = $this->binaryColumns($pdo, $quoted);

        $statement = $pdo->query("SELECT * FROM {$quoted}", PDO::FETCH_NUM);

        $rows   = [];
        $bytes  = 0;
        $wrote  = false;
        $header = "INSERT INTO {$quoted} VALUES ";

        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            if (!$wrote) {
                $this->write($handle, "--\n-- Dumping data for table {$quoted}\n--\n\n");
                $wrote = true;
            }

            $values = [];
            foreach ($row as $index => $value) {
                $values[] = $this->literal($pdo, $value, $binary[$index] ?? false);
            }

            $line    = '(' . implode(',', $values) . ')';
            $rows[]  = $line;
            $bytes  += strlen($line);

            if (count($rows) >= self::ROWS_PER_INSERT || $bytes >= self::MAX_INSERT_BYTES) {
                $this->write($handle, $header . implode(',', $rows) . ";\n");
                $rows  = [];
                $bytes = 0;
            }
        }

        $statement->closeCursor();

        if ($rows) {
            $this->write($handle, $header . implode(',', $rows) . ";\n");
        }

        $this->write($handle, "\n");
    }

    /**
     * "CREATE TABLE `t` (\n ... \n) ENGINE=...", exactly as the server reports it,
     * without a trailing semicolon (the caller adds it).
     */
    private function createStatement(PDO $pdo, string $table): string
    {
        $row = $pdo->query('SHOW CREATE TABLE `' . $table . '`', PDO::FETCH_NUM)->fetch(PDO::FETCH_NUM);

        $sql = is_array($row) ? ($row[1] ?? null) : null;

        if (!is_string($sql) || !str_starts_with($sql, 'CREATE TABLE')) {
            throw new RuntimeException("Could not read the structure of table {$table}.");
        }

        // Normalise line endings so the restore reader sees clean lines.
        return rtrim(str_replace(["\r\n", "\r"], "\n", $sql));
    }

    /**
     * @return array<int,bool> column position => true when the column holds binary data
     */
    private function binaryColumns(PDO $pdo, string $quotedTable): array
    {
        $flags = [];

        foreach ($pdo->query("SHOW COLUMNS FROM {$quotedTable}", PDO::FETCH_ASSOC) as $index => $column) {
            $type = strtolower((string) ($column['Type'] ?? ''));

            $flags[$index] = (bool) preg_match('/\b(tiny|medium|long)?blob\b|\b(var)?binary\b|^bit\b/', $type);
        }

        return $flags;
    }

    private function literal(PDO $pdo, mixed $value, bool $binary): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $value = (string) $value;

        if ($binary) {
            return $value === '' ? "''" : '0x' . bin2hex($value);
        }

        // PDO::quote escapes quotes, backslashes, NUL, \n, \r and \x1a, so a
        // value can never break out of its string or span more than one line.
        return $pdo->quote($value);
    }

    private function assertSafeName(string $table): void
    {
        // Same character set the restore reader accepts for table names.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException('A table name has characters the backup cannot store safely.');
        }
    }

    /**
     * @param  resource  $handle
     */
    private function write($handle, string $text): void
    {
        if (fwrite($handle, $text) === false) {
            throw new RuntimeException('Could not write the database dump file (disk full?).');
        }
    }
}