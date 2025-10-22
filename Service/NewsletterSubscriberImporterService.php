<?php

namespace Xqueue\Maileon\Service;

use de\xqueue\maileon\api\client\contacts\Contacts;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection;
use Throwable;
use Xqueue\Maileon\Helper\ContactBuilder;
use Xqueue\Maileon\Model\Maileon\ImportService;
use Xqueue\Maileon\Model\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Xqueue\Maileon\Logger\Logger;

class NewsletterSubscriberImporterService
{
    public function __construct(
        private SubscriberCollectionFactory $subscriberCollectionFactory,
        private ContactBuilder $contactBuilder,
        private ImportService $importService,
        private Logger $logger
    ) {}

    /**
     * @throws  Throwable
     */
    public function import(
        array $storeIds,
        int $page,
        int $batchSize,
        ?callable $onProgress = null
    ): int {
        $collection = $this->buildCollection($storeIds, $page, $batchSize);

        if (count($collection) === 0) {
            return 0;
        }

        $contacts = new Contacts();
        foreach ($collection as $subscriber) {
            $contacts->addContact($this->contactBuilder->buildNlSubscriberContactObject($subscriber, []));
        }

        try {
            $this->importService->syncContacts($contacts);
            if ($onProgress) {
                $onProgress(count($collection));
            }
        } catch (Throwable $exception) {
            $this->logger->error('Maileon batch import failed', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
            throw $exception;
        }

        return count($collection);
    }

    protected function buildCollection(array $storeIds, int $page, int $batchSize): Collection
    {
        $collection = $this->subscriberCollectionFactory->create();
        $collection->addFieldToFilter('subscriber_status', ['eq' => 1])
                ->setPageSize($batchSize)
                ->setCurPage($page)
                ->setOrder('subscriber_id', 'ASC');

        if (!empty($storeIds)) {
            $collection->addFieldToFilter('store_id', ['in' => $storeIds]);
        }

        $collection->load();

        return $collection;
    }
}
