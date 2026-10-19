<?php

declare(strict_types=1);

namespace App\Command;

use App\Database\DatabaseConnection;
use App\Database\SchemaManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:database:install', description: 'Create or additively upgrade the complete existing MySQL schema without replacing records.')]
final class DatabaseInstallCommand extends Command
{
    public function __construct(private SchemaManager $schema, private DatabaseConnection $database)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('no-seed', null, InputOption::VALUE_NONE, 'Do not add original development seeds to a new empty database. Existing databases are never seeded.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $operations = $this->schema->install(!$input->getOption('no-seed'));
        foreach ($operations as $operation) {
            $io->writeln($operation);
        }
        $io->success('Database '.$this->database->getDatabaseName().' is ready. Existing records and credentials were preserved.');

        return Command::SUCCESS;
    }
}
