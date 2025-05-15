<?php

namespace Xqueue\Maileon\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Service\MarkAbandonedCartsProcessor;

class MarkAbandonedCartsCommand extends Command
{
    public function __construct(
        private MarkAbandonedCartsProcessor $processor,
        private Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('xqueue:maileon:mark-abandoned-carts');
        $this->setDescription('Mark abandoned carts from quotes');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Running Maileon mark abandoned cart processor...</info>');
        $dates = $this->processor->getFromAndToDates($this->config->getAbandonedCartHours());

        $output->writeln('Searching carts from: ' . $dates['from']);
        $output->writeln('Searching carts to: ' . $dates['to']);

        try {
            $result = $this->processor->execute();
            $output->writeln('Enabled Store IDs: ' . implode(',', $result['enabledStoreIds']));
            $output->writeln('Found quotes: ' . $result['foundQuotes']);
            $output->writeln('Saved quotes: ' . $result['savedQuotes']);
            $output->writeln('<info>Done.</info>');
            return Cli::RETURN_SUCCESS;
        } catch (Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }
}