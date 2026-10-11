<?php

namespace Tests\Feature;

use App\Exceptions\RestoreException;
use App\Services\BackupDatabaseImporter;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The SQL importer's checks. Nothing here runs SQL: scan() only READS and
 * validates. (Real execution is covered separately against a scratch database.)
 */
class BackupDatabaseImporterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('app/testing-dumps-' . bin2hex(random_bytes(4)));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function table(string $name, string $insert = '(1),(2)'): string
    {
        return "DROP TABLE IF EXISTS `$name`;\n"
            . "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n"
            . "/*!50503 SET character_set_client = utf8mb4 */;\n"
            . "CREATE TABLE `$name` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  `note` varchar(255) DEFAULT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n"
            . "/*!40101 SET character_set_client = @saved_cs_client */;\n\n"
            . "LOCK TABLES `$name` WRITE;\n/*!40000 ALTER TABLE `$name` DISABLE KEYS */;\n"
            . "INSERT INTO `$name` VALUES $insert;\n"
            . "/*!40000 ALTER TABLE `$name` ENABLE KEYS */;\nUNLOCK TABLES;\n";
    }

    private function dump(string $body, bool $complete = true): string
    {
        $head = "-- MySQL dump 10.13\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n"
            . "/*!50503 SET NAMES utf8mb4 */;\n/*!40103 SET TIME_ZONE='+00:00' */;\n"
            . "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n"
            . "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n"
            . "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n";
        $foot = "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n"
            . ($complete ? "\n-- Dump completed on 2026-09-30  9:09:18\n" : "\n");

        return $head . $body . $foot;
    }

    private function write(string $sql): string
    {
        $path = $this->dir . '/dump-' . bin2hex(random_bytes(3)) . '.sql';
        file_put_contents($path, $sql);

        return $path;
    }

    private function scan(string $sql, array $preserve = []): array
    {
        return (new BackupDatabaseImporter())->scan($this->write($sql), $preserve);
    }

    /** @return array<int,array> what the importer would actually run (kind/table/skip) */
    private function plan(string $sql, array $preserve = []): array
    {
        $method = new \ReflectionMethod(BackupDatabaseImporter::class, 'statements');
        $method->setAccessible(true);
        $set = array_fill_keys(array_map('strtolower', $preserve), true);

        return iterator_to_array($method->invoke(new BackupDatabaseImporter(), $this->write($sql), $set), false);
    }

    // ------------------------------------------------------------ accepted

    public function test_a_normal_mysqldump_is_accepted(): void
    {
        $info = $this->scan($this->dump($this->table('users') . $this->table('patients')));

        $this->assertEqualsCanonicalizing(['users', 'patients'], $info['tables']);
        $this->assertEqualsCanonicalizing(['users', 'patients'], $info['restorable']);
    }

    public function test_a_mariadb_style_multi_line_insert_is_accepted_and_runs_as_one_statement(): void
    {
        $body = $this->table('users', "\n(1,'a'),\n(2,'b; c'),\n(3,'it\\'s');")
            ;
        // table() appends "INSERT INTO `users` VALUES <insert>;" - build the MariaDB layout by hand instead.
        $body = str_replace("INSERT INTO `users` VALUES \n(1,'a'),\n(2,'b; c'),\n(3,'it\\'s');;", "INSERT INTO `users` VALUES\n(1,'a'),\n(2,'b; c'),\n(3,'it\\'s');", $body);

        $plan    = $this->plan($this->dump($body));
        $inserts = collect($plan)->where('kind', 'insert');

        $this->assertCount(1, $inserts);
        $this->assertStringContainsString("(3,'it\\'s');", $inserts->first()['sql']);
        $this->assertStringStartsWith('INSERT INTO `users` VALUES', $inserts->first()['sql']);
        $this->assertSame(['users'], $this->scan($this->dump($body))['tables']);
    }

    #[DataProvider('hostileMultiLineInserts')]
    public function test_a_multi_line_insert_cannot_smuggle_anything_in(string $insert): void
    {
        $this->expectException(RestoreException::class);

        $this->scan($this->dump($this->table('users', '(0)') . $insert . "\n"));
    }

    public static function hostileMultiLineInserts(): array
    {
        return [
            'a statement in the middle'      => ["INSERT INTO `users` VALUES\n(1,'a'),\nDROP TABLE `users`;"],
            'a second statement on the last row' => ["INSERT INTO `users` VALUES\n(1,'a'),\n(2,'b'); DROP TABLE `users`;"],
            'a row line that does not look like a row' => ["INSERT INTO `users` VALUES\n(1,'a'),\n  (2,'b');"],
            'never terminated'               => ["INSERT INTO `users` VALUES\n(1,'a'),\n(2,'b'),"],
            'into a table the dump never created' => ["INSERT INTO `nowhere` VALUES\n(1),\n(2);"],
        ];
    }

    public function test_the_real_dump_from_the_users_backup_is_accepted_when_available(): void
    {
        $zipPath = base_path('storage/app/private/backups');
        $zips    = glob($zipPath . '/clinic_backup_*.zip') ?: [];

        if ($zips === []) {
            $this->markTestSkipped('No real backup archive is available in this environment.');
        }

        $zip = new \ZipArchive();
        $zip->open($zips[0]);
        $sql = (string) $zip->getFromName('database/database.sql');
        $zip->close();

        $info = $this->scan($sql, ['backup_logs', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs']);

        $this->assertGreaterThan(10, count($info['tables']));
        $this->assertLessThan(count($info['tables']), count($info['restorable']));
    }

    public function test_preserved_tables_are_checked_but_never_executed_and_matching_ignores_case(): void
    {
        $sql  = $this->dump($this->table('users') . $this->table('backup_logs') . $this->table('Sessions'));
        $plan = $this->plan($sql, ['BACKUP_LOGS', 'sessions']);

        $skipped = collect($plan)->where('skip', true)->pluck('table')->filter()->unique()->map('strtolower')->values()->all();
        $this->assertEqualsCanonicalizing(['backup_logs', 'sessions'], $skipped);

        $executedTables = collect($plan)->where('skip', false)->pluck('table')->filter()->unique()->values()->all();
        $this->assertSame(['users'], $executedTables);

        $info = $this->scan($sql, ['backup_logs', 'sessions']);
        $this->assertSame(['users'], $info['restorable']);
    }

    public function test_semicolons_and_quotes_inside_values_are_fine(): void
    {
        $insert = "(1,'Dr. A; Dr. B'),(2,'it\\'s; fine'),(3,'say \"hi\"; ok'),(4,'a''b; c')";

        $info = $this->scan($this->dump($this->table('notes', $insert)));

        $this->assertSame(['notes'], $info['tables']);
    }

    public function test_mariadb_and_newer_mysql_housekeeping_lines_are_accepted(): void
    {
        $sql = "/*M!999999\\- enable the sandbox mode */ \n"
            . $this->dump(
                "/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;\n"
                . "/*!50717 SELECT COUNT(*) INTO @rocksdb_has_p_s_session_variables FROM performance_schema.session_variables WHERE VARIABLE_NAME='rocksdb_skip_fill_cache' */;\n"
                . $this->table('users')
                . "/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;\n"
            );

        $this->assertSame(['users'], $this->scan($sql)['tables']);
    }

    public function test_unknown_set_statements_are_skipped_never_executed(): void
    {
        $sql = $this->dump(
            "SET @@GLOBAL.GTID_PURGED='abc:1-9';\nSET PASSWORD = 'hacked';\nSET GLOBAL max_connections = 1;\n" . $this->table('users')
        );

        $plan = $this->plan($sql);
        $sets = collect($plan)->where('kind', 'set');

        foreach ($sets as $set) {
            if (str_contains($set['sql'], 'PASSWORD') || str_contains($set['sql'], 'GLOBAL') || str_contains($set['sql'], 'GTID')) {
                $this->assertTrue($set['skip'], "'{$set['sql']}' must never be executed");
            }
        }

        $this->assertNotEmpty($sets->where('skip', false), 'the harmless session settings are still executed');
    }

    public function test_dangerous_set_statements_hidden_in_conditional_comments_are_skipped_too(): void
    {
        $sql  = $this->dump("/*!40101 SET PASSWORD = 'hacked' */;\n/*!50000 SET GLOBAL max_connections = 1 */;\n/*!40101 SET @@GLOBAL.read_only = 0 */;\n" . $this->table('users'));
        $sets = collect($this->plan($sql))->where('kind', 'set')->filter(
            fn ($s) => str_contains($s['sql'], 'PASSWORD') || str_contains($s['sql'], 'GLOBAL')
        );

        $this->assertCount(3, $sets);
        foreach ($sets as $set) {
            $this->assertTrue($set['skip'], "'{$set['sql']}' must never be executed");
        }
    }

    // ------------------------------------------------------------ rejected

    #[DataProvider('hostileStatements')]
    public function test_anything_outside_the_allowlist_rejects_the_whole_dump(string $statement): void
    {
        $this->expectException(RestoreException::class);

        $this->scan($this->dump($this->table('users') . $statement . "\n"));
    }

    public static function hostileStatements(): array
    {
        return [
            'drop database'         => ['DROP DATABASE clinic;'],
            'create database'       => ['CREATE DATABASE evil;'],
            'use another database'  => ['USE mysql;'],
            'grant privileges'      => ["GRANT ALL PRIVILEGES ON *.* TO 'x'@'%';"],
            'create user'           => ["CREATE USER 'x'@'%' IDENTIFIED BY 'p';"],
            'load data'             => ["LOAD DATA INFILE '/etc/passwd' INTO TABLE users;"],
            'select into outfile'   => ["SELECT '<?php' INTO OUTFILE '/var/www/x.php';"],
            'delimiter (trigger)'   => ['DELIMITER ;;'],
            'create trigger'        => ['CREATE TRIGGER t BEFORE INSERT ON users FOR EACH ROW SET NEW.id = 1;'],
            'trigger in a comment'  => ['/*!50003 CREATE TRIGGER t BEFORE INSERT ON users FOR EACH ROW SET NEW.id = 1 */;'],
            'view in a comment'     => ['/*!50001 CREATE VIEW v AS SELECT * FROM users */;'],
            'drop view'             => ['DROP VIEW IF EXISTS `v`;'],
            'truncate'              => ['TRUNCATE TABLE `users`;'],
            'update'                => ["UPDATE users SET role='Admin';"],
            'delete'                => ['DELETE FROM users;'],
            'alter table'           => ['ALTER TABLE `users` DROP COLUMN `id`;'],
            'insert into unknown'   => ['INSERT INTO `never_created` VALUES (1);'],
            'second statement on the same line' => ['INSERT INTO `users` VALUES (9,NULL); DROP TABLE `users`;'],
            'second statement after quotes'     => ["INSERT INTO `users` VALUES (9,'x'); -- ok\nDROP TABLE `patients`;"],
            'drop of a non-table'   => ['DROP TABLE `a`, `b`;'],
        ];
    }

    public function test_a_second_statement_hidden_inside_a_create_table_block_is_rejected(): void
    {
        $block = "DROP TABLE IF EXISTS `t`;\nCREATE TABLE `t` (\n  `id` int,\n  `x` int DEFAULT 1; DROP TABLE `users`\n) ENGINE=InnoDB;\n";

        $this->expectException(RestoreException::class);

        $this->scan($this->dump($block));
    }

    public function test_an_unfinished_create_table_is_rejected(): void
    {
        $this->expectException(RestoreException::class);

        $this->scan($this->dump("CREATE TABLE `t` (\n  `id` int,\n  PRIMARY KEY (`id`)\n"));
    }

    public function test_a_dump_that_was_cut_short_is_rejected(): void
    {
        $this->expectException(RestoreException::class);
        $this->expectExceptionMessage('incomplete');

        $this->scan($this->dump($this->table('users'), false));
    }

    public function test_an_empty_or_table_less_dump_is_rejected(): void
    {
        $this->expectException(RestoreException::class);

        $this->scan($this->dump(''));
    }

    public function test_a_preserved_table_can_never_be_dropped_by_the_dump(): void
    {
        $plan = $this->plan($this->dump($this->table('backup_logs', "(1,'x')")), ['backup_logs']);

        $this->assertNotEmpty($plan);
        foreach ($plan as $statement) {
            if (in_array($statement['kind'], ['drop', 'create', 'insert'], true)) {
                $this->assertTrue($statement['skip'], 'backup_logs statements must all be skipped');
            }
        }
    }
}
