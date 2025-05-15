<?php
 
namespace Xqueue\Maileon\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;
use Xqueue\Maileon\Api\MaileonWebhookInterface;
use Xqueue\Maileon\Helper\SubscriberHelper;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Helper\Config;
use Magento\Framework\Exception\NoSuchEntityException;
 
class MaileonWebhook implements MaileonWebhookInterface
{
    public function __construct(
        private Config $config,
        private StoreManagerInterface $storeManager,
        private SubscriberHelper $subscriberHelper,
        private Logger $logger
    ) {}

    /**
     * @inheritdoc
     */
    public function getUnsubscribeWebhook(string $email, string $token, ?string $storeview_id = null): string
    {
        $storeId = $storeview_id;
        $email = $this->normalizeEmail($email);

        if (!$this->isValidToken($token, $this->config->getUnsubscribeWebhookToken())) {
            return $this->errorResponse('Bad unsubscriber token!');
        }

        if (empty($email) || empty($token)) {
            return $this->errorResponse('Empty email or token params!');
        }

        try {
            if ($storeId) {
                return $this->unsubscribeFromStore($email, $storeId);
            }

            if ($this->config->isUnsubscribeAllEnabled()) {
                return $this->unsubscribeFromAllStores($email);
            }

            return $this->errorResponse('Storeview id is empty and unsubscribe all email disabled! Email: ' . $email);

        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * @inheritdoc
     */
    public function getDoiConfirmWebhook(string $email, string $token, string $storeview_id): string
    {
        $storeId = $storeview_id;
        $email = $this->normalizeEmail($email);

        if (!$this->isValidToken($token, $this->config->getDOIWebhookToken())) {
            return $this->errorResponse('Bad DOI token!');
        }

        if (empty($email) || empty($token)) {
            return $this->errorResponse('Empty email or token params!');
        }

        try {
            if (!$this->isValidStoreId($storeId)) {
                return $this->errorResponse("Invalid store ID: $storeId");
            }

            $this->storeManager->setCurrentStore($storeId);

            $subscriber = $this->subscriberHelper->getSubscriberByEmail($email);

            if ($subscriber) {
                $subscriber->confirm($subscriber->getCode());
                return $this->successResponse("Confirmed: $email");
            }

            return $this->errorResponse("Not existing subscriber!");
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return $this->errorResponse($exception->getMessage());
        }
    }

    private function normalizeEmail(string $email): string
    {
        return preg_replace('/\s+/', '+', $email);
    }

    private function isValidToken(string $token, string $expectedToken): bool
    {
        return $token === $expectedToken;
    }

    private function isValidStoreId(string $storeId): bool
    {
        try {
            $this->storeManager->getStore($storeId);
            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }

    /**
     * @throws LocalizedException
     */
    private function unsubscribeFromStore(string $email, string $storeId): string
    {
        if (!$this->isValidStoreId($storeId)) {
            return $this->errorResponse("Invalid store ID: $storeId");
        }

        $this->storeManager->setCurrentStore($storeId);
        $subscriber = $this->subscriberHelper->getSubscriberByEmail($email);

        if (!$subscriber) {
            return $this->errorResponse("Subscriber not found for email: $email, Store Id: $storeId");
        }

        if ($subscriber->isSubscribed()) {
            $subscriber->unsubscribe();
            return $this->successResponse("Unsubscribed: $email, Store Id: $storeId");
        }

        return $this->errorResponse("Subscriber is not subscribed: $email, Store Id: $storeId");
    }

    /**
     * @throws LocalizedException
     */
    private function unsubscribeFromAllStores(string $email): string
    {
        $unsubscribedFrom = [];

        foreach ($this->storeManager->getStores() as $store) {
            $this->storeManager->setCurrentStore($store->getId());
            $subscriber = $this->subscriberHelper->getSubscriberByEmail($email);

            if ($subscriber && $subscriber->isSubscribed()) {
                $subscriber->unsubscribe();
                $unsubscribedFrom[] = $store->getId();
            }
        }

        if (!empty($unsubscribedFrom)) {
            return $this->successResponse("Unsubscribed: $email, Store Ids: " . implode(',', $unsubscribedFrom));
        }

        return $this->errorResponse("Subscriber not found or already unsubscribed in all stores for email: $email");
    }

    private function successResponse(string $message): string
    {
        return json_encode(['success' => true, 'message' => $message]);
    }

    private function errorResponse(string $message): string
    {
        return json_encode(['success' => false, 'message' => $message]);
    }
}
