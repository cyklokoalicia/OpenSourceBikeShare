<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\Db\DbInterface;
use Nelmio\Alice\Loader\SimpleFileLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'load:fixtures', description: 'Load test fixtures into the database')]
class LoadFixturesCommand extends Command
{
    private string $appEnvironment;
    private string $projectDir;
    private string $dbDatabase;
    private SimpleFileLoader $fixturesLoader;
    private DbInterface $db;

    public function __construct(
        string $appEnvironment,
        string $projectDir,
        string $dbDatabase,
        SimpleFileLoader $fixturesLoader,
        DbInterface $db
    ) {
        $this->appEnvironment = $appEnvironment;
        $this->projectDir = $projectDir;
        $this->dbDatabase = $dbDatabase;
        $this->fixturesLoader = $fixturesLoader;
        $this->db = $db;

        parent::__construct('load:fixtures');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($this->appEnvironment, ['test'], true)) {
            $output->writeln('<error>This command can only be run in the test environment.</error>');
            return Command::FAILURE;
        }

        $this->db->query('DROP DATABASE IF EXISTS `' . $this->dbDatabase .'`;');
        $this->db->query('CREATE DATABASE `' . $this->dbDatabase . '` CHARACTER SET utf8 COLLATE utf8_general_ci;');
        $this->db->query('USE `' . $this->dbDatabase . '`');
        $initSql = file_get_contents($this->projectDir . '/docker-data/mysql/create-database.sql');
        $this->db->exec($initSql);

        $objectSet = $this->fixturesLoader->loadFile($this->projectDir . '/tests/fixtures/fixtures.yml');

        foreach ($objectSet->getObjects() as $object) {
            $tableName = $object->tableName;
            unset($object->tableName);

            $fields = array_keys((array)$object);
            $values = array_values((array)$object);

            $fieldsStr = '`' . implode('`, `', $fields) . '`';
            $valuesStr = '"' . implode('", "', array_map(function ($val) {
                    return $val === null ? 'NULL' : addslashes((string)$val);
                }, $values)) . '"';

            $sql = "INSERT INTO `{$tableName}` ({$fieldsStr}) VALUES ({$valuesStr});";
            dump($sql);
            $this->db->query($sql);
        }

        return Command::SUCCESS;
    }
}
