<?php

declare(strict_types=1);

namespace App\Command;

use App\Database\BackupManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:database:backup', description: 'Export all database tables, records, triggers, views, routines and events to a new private SQL file.')]
final class DatabaseBackupCommand extends Command
{
    public function __construct(private BackupManager $backup)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'A new SQL file outside public/ and version control.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counts = $this->backup->backup($input->getArgument('file'));
        (new SymfonyStyle($input, $output))->success(sprintf('Exported %d tables and %d records, including database objects. Keep this backup private.', count($counts), array_sum($counts)));

        return Command::SUCCESS;
    }
}
