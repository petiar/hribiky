<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:timelapse:fetch',
    description: 'Stiahne screenshot stránky hribiky.sk z thum.io API a uloží ho do /uploads/timelapse',
)]
class FetchTimelapseCommand extends Command
{
    private string $targetDir;

    public function __construct(string $projectDir)
    {
        parent::__construct();
        $this->targetDir = $projectDir . '/public/uploads/timelapse';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sťahujem timelapse screenshot...');

        $url = 'https://image.thum.io/get/width/1000/noanimate/https://hribiky.sk';
        $client = HttpClient::create();

        try {
            $response = $client->request('GET', $url);

            if (200 !== $response->getStatusCode()) {
                $io->error('API vrátila chybu: ' . $response->getStatusCode());
                return Command::FAILURE;
            }

            $content = $response->getContent();

            // Uisti sa, že adresár existuje
            if (!is_dir($this->targetDir)) {
                mkdir($this->targetDir, 0775, true);
            }

            // Použi timestamp ako názov súboru
            $filename = sprintf('timelapse-%s.png', date('Y-m-d_H-i-s'));
            $path = $this->targetDir . '/' . $filename;

            file_put_contents($path, $content);

            $io->success("Screenshot uložený: $path");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error('Chyba pri sťahovaní: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
