<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:db:init', description: 'Initializes the SQLite database schema')]
class InitDatabaseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaFile = $this->projectDir . '/database/schema.sql';

        if (!file_exists($schemaFile)) {
            $output->writeln('<error>Schema file not found.</error>');
            return Command::FAILURE;
        }

        $sql = file_get_contents($schemaFile);

        if ($sql === false) {
            $output->writeln('<error>Unable to read schema file.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Initializing database schema...</info>');

        $this->connection->executeStatement('PRAGMA foreign_keys = ON');

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $this->connection->executeStatement($statement);
            }
        }

        $output->writeln('<info>Database initialized successfully.</info>');

        return Command::SUCCESS;
    }
}
