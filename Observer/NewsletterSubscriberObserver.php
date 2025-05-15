<?php

namespace Xqueue\Maileon\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Helper\SubscriberHelper;
use Xqueue\Maileon\Logger\Logger;

class NewsletterSubscriberObserver implements ObserverInterface
{
    public function __construct(
        private SubscriberHelper $subscriberHelper,
        private Config $config,
        private Logger $logger
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            /** @var Subscriber $subscriber */
            $subscriber = $observer->getEvent()->getData('subscriber');

            if (! $this->config->isNewsletterModulEnabled($subscriber->getStoreId())) {
                return;
            }

            switch ($subscriber->getStatus()) {
                case Subscriber::STATUS_UNSUBSCRIBED:
                    $this->subscriberHelper->unsubscribeContact($subscriber);
                    break;
                default:
                    $this->subscriberHelper->updateOrCreateContactFromSubscriber($subscriber);
                    break;
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
