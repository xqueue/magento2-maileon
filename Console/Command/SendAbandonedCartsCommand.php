<?php

namespace Xqueue\Maileon\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Xqueue\Maileon\Service\SendAbandonedCartsProcessor;

class SendAbandonedCartsCommand extends Command
{
    public function __construct(
        private SendAbandonedCartsProcessor $processor
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('xqueue:maileon:send-abandoned-carts')
            ->setDescription('Send abandoned carts to Maileon');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Running Maileon send abandoned cart processor...</info>');

        try {
            $result = $this->processor->execute();
            $output->writeln('Sent abandoned carts to Maileon: ' . (string) $result['sentCart']);
            $output->writeln('<info>Done.</info>');
            return Cli::RETURN_SUCCESS;
        } catch (Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }
}
