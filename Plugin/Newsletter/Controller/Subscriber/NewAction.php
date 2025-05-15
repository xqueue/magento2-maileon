<?php

namespace Xqueue\Maileon\Plugin\Newsletter\Controller\Subscriber;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Newsletter\Controller\Subscriber\NewAction as BaseNewAction;
use Throwable;
use Xqueue\Maileon\Helper\SubscriberHelper;
use Xqueue\Maileon\Logger\Logger;
use Magento\Store\Model\StoreManagerInterface;
use Xqueue\Maileon\Helper\Config;

class NewAction
{
    public function __construct(
        private StoreManagerInterface $storeManager,
        private SubscriberHelper $subscriberHelper,
        private Config $config,
        private Logger $logger
    ) {}

    public function afterExecute(BaseNewAction $subject, $result)
    {
        try {
            $storeId = $this->getCurrentStoreId();

            if (! $this->config->isNewsletterModulEnabled($storeId)) {
                return $result;
            }

            $request = $subject->getRequest();
            $email = $request->getParam('email');

            if (!empty($email)) {
                if (!$this->subscriberHelper->emailIsExists($email)) {
                    $validatedCustomParams = $this->extractAndValidate($request->getParams());

                    if (!empty($validatedCustomParams['standard']) || !empty($validatedCustomParams['custom'])) {
                        $subscriber = $this->subscriberHelper->getSubscriberByEmail($email);
                        $this->subscriberHelper->updateOrCreateContactFromSubscriber($subscriber, $validatedCustomParams);
                    }
                }
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        return $result;
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getCurrentStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    protected function extractAndValidate(array $params): array
    {
        $standard = [];
        $custom = [];

        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'standard_')) {
                $field = strtoupper(substr($key, 9)); // remove "standard_"

                if (!isset(Config::STANDARD_FIELDS[$field])) {
                    continue; // unknown standard field
                }

                $type = Config::STANDARD_FIELDS[$field];
                $validated = $this->validateStandardField($type, $value);

                if ($validated !== null) {
                    $standard[$field] = $validated;
                }

            } elseif (str_starts_with($key, 'custom_')) {
                $field = substr($key, 7); // remove "custom_"
                if (is_scalar($value)) {
                    $custom[$field] = mb_substr((string)$value, 0, 255);
                }
            }
        }

        return [
            'standard' => $standard,
            'custom'   => $custom,
        ];
    }

    private function validateStandardField(string $type, mixed $value): mixed
    {
        switch ($type) {
            case 'string':
                if (is_string($value) && $value !== '') {
                    return mb_substr($value, 0, 255);
                }
                return null;

            case 'enum':
                $lower = strtolower((string)$value);
                return in_array($lower, Config::ALLOWED_GENDERS, true) ? $lower : null;

            case 'date':
                return $this->isValidDateFormat($value) ? $value : null;

            default:
                return null;
        }
    }

    private function isValidDateFormat(string $value): bool
    {
        // Only accept YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = explode('-', $value);
        return checkdate((int)$month, (int)$day, (int)$year);
    }
}
