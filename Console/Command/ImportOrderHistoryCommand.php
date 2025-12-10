<?php

namespace Xqueue\Maileon\Console\Command;

use DateTime;
use DateTimeImmutable;
use Exception;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\StoreRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Service\OrderHistoryImportService;

class ImportOrderHistoryCommand extends Command
{
    public function __construct(
        private Config $config,
        private OrderHistoryImportService $orderHistoryImportService,
        private StoreRepositoryInterface $storeRepository,
        private State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('xqueue-maileon:import-orders-history')
            ->setDescription('Imports orders history in batches to Maileon as contact events.')
            ->addOption(
                'store-view',
                null,
                InputOption::VALUE_OPTIONAL,
                'Store view(s) by ID or name (comma-separated if multiple)'
            )
            ->addOption(
                'page',
                null,
                InputOption::VALUE_OPTIONAL,
                'Page to start from (1-based)',
                1
            )
            ->addOption(
                'from',
                null,
                InputOption::VALUE_REQUIRED,
                'Export orders created from this date (Y-m-d)'
            )
            ->addOption(
                'to',
                null,
                InputOption::VALUE_OPTIONAL,
                'Export orders up to this date (Y-m-d)',
                date('Y-m-d')
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (LocalizedException) {
            $output->writeln('Area code already set. Continue...');
        }

        if (!$this->config->isOrderHistoryImportEnabled()) {
            $output->writeln('<info>This command is not enabled at the config!</info>');
            return Command::SUCCESS;
        }

        $page = max(1, (int) $input->getOption('page'));
        $storeViewInput = $input->getOption('store-view');
        $fromOption = $input->getOption('from');
        $toOption = $input->getOption('to');
        $storeIds = [];

        if (!$this->validateRequiredOptions($storeViewInput, $fromOption, $output)) {
            return Command::FAILURE;
        }

        if (!empty($storeViewInput)) {
            $rawInputs = array_filter(array_map('trim', explode(',', (string)$storeViewInput)));

            foreach ($rawInputs as $value) {
                try {
                    if (is_numeric($value)) {
                        $store = $this->storeRepository->getById((int)$value);
                    } else {
                        $store = $this->storeRepository->get($value);
                    }
                    $storeIds[] = $store->getId();
                } catch (Throwable) {
                    $output->writeln("<error>Invalid store view: $value</error>");
                    return Command::FAILURE;
                }
            }
        }

        if (!$this->validateDateFormats($fromOption, $toOption, $output)) {
            return Command::FAILURE;
        }

        if (!$this->confirmExecution($input, $output)) {
            $output->writeln('<comment>Command aborted by user.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Starting order import to Maileon...</info>');
        $output->writeln("Page: $page");
        $output->writeln("Store view(s): " . implode(', ', $storeIds));
        $output->writeln("From: $fromOption");
        $output->writeln("To: $toOption");

        try {
            [$fromDt, $toDt] = $this->normalizeDateRange($fromOption, $toOption);
            $total = $this->orderHistoryImportService->countOrders($storeIds, $fromDt, $toDt);
            $output->writeln("<info>Total orders to import: $total</info>");
            $output->writeln('');

            $progressBar = new ProgressBar($output, $total);
            $progressBar->setFormat('Importing orders: %current%/%max% [%bar%] %percent:3s%%');
            $progressBar->start();

            $importResult = $this->orderHistoryImportService->importOrderHistory(
                $storeIds,
                $fromDt,
                $toDt,
                $progressBar,
                $page
            );

            $progressBar->finish();
            $output->writeln('');
            $output->writeln($importResult . ' orders imported into Maileon.');
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $output->writeln('');
            $output->writeln('An error has occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function validateRequiredOptions(?string $storeIds, ?string $from, OutputInterface $output): bool
    {
        if (!$storeIds) {
            $output->writeln('<error>The --store-view option is required.</error>');
            return false;
        }

        if (!$from) {
            $output->writeln('<error>The --from option is required.</error>');
            return false;
        }

        return true;
    }

    private function validateDateFormats(string $from, string $to, OutputInterface $output): bool
    {
        if (!$this->isValidDate($from)) {
            $output->writeln('<error>Invalid from date format. Expected format: Y-m-d</error>');
            return false;
        }

        if (!$this->isValidDate($to)) {
            $output->writeln('<error>Invalid to date format. Expected format: Y-m-d</error>');
            return false;
        }

        return true;
    }

    private function confirmExecution(InputInterface $input, OutputInterface $output): bool
    {
        $eventTypeName = Config::ORDER_CONFIRM_TX_NAME;

        $warningText = <<<TEXT
        <comment>⚠️ WARNING: Orders will be imported by the process into this contact event types in Maileon: $eventTypeName</comment>
        
        If trigger emails are assigned to these contact event types in Maileon, an email will be sent to the customer for each order. 
        Please check that there are no trigger emails associated with this transaction types before running this command!
        
        Depending on the amount of data, this command can take a very long time to run,
        so we recommend that you run it in smaller chunks using the date and shop filters.
        TEXT;

        $output->writeln($warningText);
        $output->writeln('');
        $helper = $this->getHelper('question');
        $question = new Question('Are you sure you want to continue? (yes/no): ', 'no');
        $answer = strtolower($helper->ask($input, $output, $question));

        return $answer === 'yes';
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     * @throws Exception
     */
    private function normalizeDateRange(string $from, ?string $to): array
    {
        $fromDt = $this->toDateTimeImmutable($from, '00:00:00');

        if ($to === null || trim($to) === '') {
            $toDt = new DateTimeImmutable('now');
        } else {
            $toDt = $this->toDateTimeImmutable($to, '23:59:59');
        }

        if ($fromDt > $toDt) {
            [$fromDt, $toDt] = [$toDt, $fromDt];
        }

        return [$fromDt, $toDt];
    }

    /**
     * @throws Exception
     */
    private function toDateTimeImmutable(string $date, string $defaultTime): DateTimeImmutable
    {
        $date = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $date .= ' ' . $defaultTime;
        }

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date)
            ?: new DateTimeImmutable($date);
    }
}
