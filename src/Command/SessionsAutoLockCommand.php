<?php

namespace App\Command;

use App\Service\SessionAutoLockService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sessions:auto-lock',
    description: 'Verrouille (transition lock) toutes les sessions OPEN dont la date limite est dépassée',
)]
class SessionsAutoLockCommand extends Command
{
    public function __construct(
        private readonly SessionAutoLockService $autoLockService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->autoLockService->lockAllExpired();
        $io->success(sprintf('%d session(s) verrouillée(s) automatiquement.', $count));

        return Command::SUCCESS;
    }
}
