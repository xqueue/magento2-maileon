<?php

namespace Xqueue\Maileon\Helper;

use de\xqueue\maileon\api\client\contacts\Permission;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Newsletter\Model\Subscriber;
use Xqueue\Maileon\Model\Maileon\ContactService;
use Xqueue\Maileon\Model\Subscriber\CollectionFactory as SubscriberCollectionFactory;

class SubscriberHelper
{
    public function __construct(
        private Config $config,
        private ContactBuilder $contactBuilder,
        private SubscriberCollectionFactory $subscriberCollectionFactory
    ) {}

    public function getSubscriberByEmail(string $email): ?Subscriber
    {
        $collection = $this->subscriberCollectionFactory->create()
            ->addFieldToFilter('subscriber_email', $email)
            ->setPageSize(1)
            ->load();

        $item = $collection->getFirstItem();

        if ($item->getId() && $item instanceof Subscriber) {
            return $item;
        }

        return null;
    }

    public function emailIsExists(string $email): bool
    {
        $collection = $this->subscriberCollectionFactory->create()
            ->addFieldToFilter('subscriber_email', $email)
            ->addFieldToFilter('subscriber_status', Subscriber::STATUS_SUBSCRIBED)
            ->setPageSize(1)
            ->load();

        return (bool) $collection->getSize();
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function updateOrCreateContactFromSubscriber(Subscriber $subscriber, array $customParams = []): bool
    {
        if (empty($customParams)) {
            $contactService = new ContactService(
                $this->config->getApiKey($subscriber->getStoreId()),
                $subscriber->getEmail(),
                $this->config->isDOIProcessEnabled($subscriber->getStoreId()),
                $this->config->isDOIPlusProcessEnabled($subscriber->getStoreId()),
                $this->config->getNlDOIKey($subscriber->getStoreId())
            );
            $permission = $this->config->getNlPermission($subscriber->getStoreId()) ?? 'none';
        } else {
            $contactService = new ContactService(
                $this->config->getApiKey($subscriber->getStoreId()),
                $subscriber->getEmail(),
                false,
                false
            );
            $permission = 'none';
        }

        $contact = $this->contactBuilder->buildNlSubscriberContactObject($subscriber, $customParams);
        $contact->permission = Permission::getPermission($permission);

        return $contactService->createOrUpdateMaileonContact($contact);
    }

    public function unsubscribeContact(Subscriber $subscriber): bool
    {
        $contactService = new ContactService(
            $this->config->getApiKey($subscriber->getStoreId()),
            $subscriber->getEmail(),
            $this->config->isDOIProcessEnabled($subscriber->getStoreId()),
            $this->config->isDOIPlusProcessEnabled($subscriber->getStoreId()),
            $this->config->getNlDOIKey($subscriber->getStoreId())
        );

        return $contactService->unsubscribeMaileonContact();
    }
}
