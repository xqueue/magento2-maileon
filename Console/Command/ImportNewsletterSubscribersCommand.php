<?php

namespace Xqueue\Maileon\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\StoreRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Model\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Xqueue\Maileon\Service\NewsletterSubscriberImporterService;

class ImportNewsletterSubscribersCommand extends Command
{
    private const BATCH_SIZE = 1000;

    public function __construct(
        private Config $config,
        private NewsletterSubscriberImporterService $newsletterSubscriberImporterService,
        private SubscriberCollectionFactory $subscriberCollectionFactory,
        private StoreRepositoryInterface $storeRepository,
        private State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('xqueue-maileon:import-newsletter-subscribers')
            ->setDescription('Imports newsletter subscribers in batches to Maileon.')
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

        if (! $this->config->isNewsletterSubscriberImportEnabled()) {
            $output->writeln('<info>This command is not enabled at the config!</info>');
            return Command::SUCCESS;
        }

        $page = max(1, (int) $input->getOption('page'));
        $storeViewInput = $input->getOption('store-view');
        $storeIds = [];

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

        try {
            $countAllSubscribers = $this->countMatchingSubscribers($storeIds);
            if ($countAllSubscribers === 0) {
                $output->writeln('<info>No subscribers found for the given parameters.</info>');
                return Command::SUCCESS;
            }

            $output->writeln("<info>Total subscribers to import: $countAllSubscribers</info>");
        } catch (Throwable $e) {
            $output->writeln('<error>Failed to count subscribers:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $importedTotal = 0;

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('Importing: [%bar%] %current% recipients imported');
        $progressBar->start();

        while (true) {
            try {
                $imported = $this->newsletterSubscriberImporterService->import(
                    $storeIds,
                    $page,
                    self::BATCH_SIZE,
                    fn($count) => $progressBar->advance($count)
                );
            } catch (Throwable $e) {
                $output->writeln('');
                $output->writeln('<error>Import failed at offset ' . $page . ':</error>');
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                $output->writeln('<error>File: ' . $e->getFile() . '</error>');
                $output->writeln('<error>Line: ' . $e->getLine() . '</error>');
                return Command::FAILURE;
            }

            if ($imported === 0) {
                break;
            }

            $page++;
            $importedTotal += $imported;

            if ($imported < self::BATCH_SIZE) {
                break;
            }
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln(sprintf('<info>Import completed. Total recipients imported: %d</info>', $importedTotal));

        return Command::SUCCESS;
    }

    public function countMatchingSubscribers(array $storeIds): int
    {
        $collection = $this->subscriberCollectionFactory->create()
            ->addFieldToFilter('subscriber_status', 1);

        if (!empty($storeIds)) {
            $collection->addFieldToFilter('store_id', ['in' => $storeIds]);
        }

        return $collection->getSize();
    }
}