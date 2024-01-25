<?php

namespace App\Command;

use App\Service\CrawlerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'streams:api:build',
    description: 'Build json streams by web scrapping',
)]
class StreamsApiBuildCommand extends Command
{
    public function __construct(
        private CrawlerService $crawler
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $db = [
            'streams' => $this->crawler->build()
        ];
        
        $edb = json_encode($db);
        file_put_contents('db.json', $edb);
        $io->success('Streams api build successfully !');

        return Command::SUCCESS;
    }
}
