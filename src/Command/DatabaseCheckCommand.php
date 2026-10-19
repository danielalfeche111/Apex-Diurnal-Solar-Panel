<?php

declare(strict_types=1);

namespace App\Command;

use App\Database\SchemaManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:database:check', description: 'Read-only check of every application table, column, trigger and row count.')]
final class DatabaseCheckCommand extends Command
{
    public function __construct(private SchemaManager $schema)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->schema->check();
        $rows = [];
        foreach ($result['tables'] as $table => $count) {
            $rows[] = [$table, $count];
        }
        $io->table(['Table', 'Records'], $rows);
        if ($result['problems'] !== []) {
            $io->error($result['problems']);

            return Command::FAILURE;
        }
        $io->success('All 14 application tables, required columns and both order history triggers are present.');

        return Command::SUCCESS;
    }
}
