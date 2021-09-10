<?php

/**
 * Createe Maileon Transaction
 */

namespace Xqueue\Maileon\Model\Maileon;

use de\xqueue\maileon\api\client\transactions\DataType;
use de\xqueue\maileon\api\client\transactions\TransactionsService;
use de\xqueue\maileon\api\client\transactions\TransactionType;
use de\xqueue\maileon\api\client\transactions\AttributeType;
use de\xqueue\maileon\api\client\transactions\Transaction;
use de\xqueue\maileon\api\client\transactions\ContactReference;
use de\xqueue\maileon\api\client\MaileonAPIException;
use Xqueue\Maileon\Model\Maileon\ContactCreate;

class TransactionCreate
{
    /**
     * Maileon API key
     * @var string $apikey
     */
    private $apikey;

    /**
     * Print CURL debug data
     * @var string $print_curl
     */
    private $print_curl;

    /**
     * Maileon config
     * @var array $maileon_config
     */
    private $maileon_config;

    private $logger;


    /**
     * @param string $apikey   Maileon API key
     */
    public function __construct($apikey, $print_curl)
    {
        $this->apikey = $apikey;
        $this->print_curl = filter_var($print_curl, FILTER_VALIDATE_BOOLEAN);

        $this->maileon_config = array(
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->apikey,
            'TIMEOUT' => 35
        );

        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');
    }

    public function sendOrderTransaction(
        $email,
        $transaction_name,
        $content
    ) {
        $transactionsService = new TransactionsService($this->maileon_config);
        $transactionsService->setDebug($this->print_curl);

        $transaction = new Transaction();
        $transaction->contact = new ContactReference();
        $transaction->contact->email = $email;

        $existsTransactionType = $this->existsTransactionType($transaction_name);

        if (!$existsTransactionType) {
            $transactionType = $this->setTransactionType($transaction_name);

            if (!$transactionType) {
                $this->logger->error(
                    'Error in TransactionType creation!'
                );
            }
        }

        $transaction->typeName = $transaction_name;

        $transaction->content = $content;
    
        try {
            $transactionsService->createTransactions(array($transaction), true, false);
        } catch (MaileonAPIException $e) {
            $this->logger->error(
                (string) $e->getMessage()
            );
        }
    }

    public function existsTransactionType($transaction_name)
    {
        $transactionsService = new TransactionsService($this->maileon_config);
        $transactionsService->setDebug($this->print_curl);

        try {
            $existsTransactionType = $transactionsService->getTransactionTypeByName($transaction_name);
        } catch (MaileonAPIException $e) {
            $this->logger->error(
                (string) $e->getMessage()
            );
        }

        if ($existsTransactionType->getStatusCode() === 404) {
            return false;
        }

        return true;
    }

    /**
     * Make Maileon transaction type for Magento
     *
     * @return object TransactionType
     */

    public function setTransactionType($transaction_name)
    {
        $transactionsService = new TransactionsService($this->maileon_config);
        $transactionsService->setDebug($this->print_curl);

        $transactionType = new TransactionType();
        $transactionType->name = $transaction_name;

        switch ($transaction_name) {
            case 'magento_orders_v2':
                $transactionType->attributes = $this->getOrderTransactionType();
                break;

            case 'magento_orders_extended_v2':
                $transactionType->attributes = $this->getOrderExtendedTransactionType();
                break;

            case 'magento_abandoned_carts_v2':
                $transactionType->attributes = $this->getCartTransactionType();
                break;

            default:
                $transactionType->attributes = $this->getOrderTransactionType();
                break;
        }

        try {
            $result = $transactionsService->createTransactionType($transactionType);
        } catch (MaileonAPIException $e) {
            $this->logger->error(
                (string) $e->getMessage()
            );
        }

        return $result->isSuccess();
    }

    private function getOrderTransactionType()
    {
        $attributes = array(
            new AttributeType(null, 'order.id', DataType::$STRING, false),
            new AttributeType(null, 'order.date', DataType::$TIMESTAMP, false),
            new AttributeType(null, 'order.status', DataType::$STRING, false),
            new AttributeType(null, 'order.estimated_delivery_time', DataType::$STRING, false),
            new AttributeType(null, 'order.estimated_delivery_date', DataType::$DATE, false),
            new AttributeType(null, 'order.product_ids', DataType::$STRING, false),
            new AttributeType(null, 'order.categories', DataType::$STRING, false),
            new AttributeType(null, 'order.brands', DataType::$STRING, false),
            new AttributeType(null, 'order.total', DataType::$FLOAT, false),
            new AttributeType(null, 'order.total_no_shipping', DataType::$FLOAT, false),
            new AttributeType(null, 'order.total_tax', DataType::$FLOAT, false),
            new AttributeType(null, 'order.total_fees', DataType::$FLOAT, false),
            new AttributeType(null, 'order.total_refunds', DataType::$FLOAT, false),
            new AttributeType(null, 'order.fees', DataType::$JSON, false),
            new AttributeType(null, 'order.refunds', DataType::$JSON, false),
            new AttributeType(null, 'order.currency', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.id', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.name', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.url', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.image_url', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.data', DataType::$STRING, false),
            new AttributeType(null, 'payment.due_date', DataType::$DATE, false),
            new AttributeType(null, 'payment.status', DataType::$STRING, false),
            new AttributeType(null, 'discount.code', DataType::$STRING, false),
            new AttributeType(null, 'discount.total', DataType::$STRING, false),
            new AttributeType(null, 'discount.rules', DataType::$JSON, false),
            new AttributeType(null, 'discount.rules_string', DataType::$STRING, false),
            new AttributeType(null, 'customer.salutation', DataType::$STRING, false),
            new AttributeType(null, 'customer.fullname', DataType::$STRING, false),
            new AttributeType(null, 'customer.firstname', DataType::$STRING, false),
            new AttributeType(null, 'customer.lastname', DataType::$STRING, false),
            new AttributeType(null, 'customer.id', DataType::$STRING, false),
            new AttributeType(null, 'order.items', DataType::$JSON, false),
            new AttributeType(null, 'shipping.address.salutation', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.firstname', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.lastname', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.phone', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.region', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.city', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.country', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.zip', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.street', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.salutation', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.firstname', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.lastname', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.phone', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.region', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.city', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.country', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.zip', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.street', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.id', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.name', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.url', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.image_url', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.tracking.code', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.tracking.url', DataType::$STRING, false),
            new AttributeType(null, 'shipping.status', DataType::$STRING, false)
        );

        $attributes = $this->addGenericFields($attributes);

        return $attributes;
    }

    private function getOrderExtendedTransactionType()
    {
        $attributes = array(
            new AttributeType(null, 'order.id', DataType::$STRING, false),
            new AttributeType(null, 'order.date', DataType::$TIMESTAMP, false),
            new AttributeType(null, 'order.status', DataType::$STRING, false),
            new AttributeType(null, 'order.estimated_delivery_time', DataType::$STRING, false),
            new AttributeType(null, 'order.estimated_delivery_date', DataType::$DATE, false),
            new AttributeType(null, 'order.product_ids', DataType::$STRING, false),
            new AttributeType(null, 'order.categories', DataType::$STRING, false),
            new AttributeType(null, 'order.brands', DataType::$STRING, false),
            new AttributeType(null, 'order.total', DataType::$FLOAT, false),
            new AttributeType(null, 'order.total_no_shipping', DataType::$FLOAT, false),
            new AttributeType(null, 'order.total_tax', DataType::$FLOAT, false),
            new AttributeType(null, 'order.total_fees', DataType::$FLOAT, false),
            new AttributeType(null, 'order.total_refunds', DataType::$FLOAT, false),
            new AttributeType(null, 'order.fees', DataType::$JSON, false),
            new AttributeType(null, 'order.refunds', DataType::$JSON, false),
            new AttributeType(null, 'order.currency', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.id', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.name', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.url', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.image_url', DataType::$STRING, false),
            new AttributeType(null, 'payment.method.data', DataType::$STRING, false),
            new AttributeType(null, 'payment.due_date', DataType::$DATE, false),
            new AttributeType(null, 'payment.status', DataType::$STRING, false),
            new AttributeType(null, 'discount.code', DataType::$STRING, false),
            new AttributeType(null, 'discount.total', DataType::$STRING, false),
            new AttributeType(null, 'discount.rules', DataType::$JSON, false),
            new AttributeType(null, 'discount.rules_string', DataType::$STRING, false),
            new AttributeType(null, 'customer.salutation', DataType::$STRING, false),
            new AttributeType(null, 'customer.fullname', DataType::$STRING, false),
            new AttributeType(null, 'customer.firstname', DataType::$STRING, false),
            new AttributeType(null, 'customer.lastname', DataType::$STRING, false),
            new AttributeType(null, 'customer.id', DataType::$STRING, false),
            new AttributeType(null, 'product.id', DataType::$STRING, false),
            new AttributeType(null, 'product.title', DataType::$STRING, false),
            new AttributeType(null, 'product.description', DataType::$STRING, false),
            new AttributeType(null, 'product.short_description', DataType::$STRING, false),
            new AttributeType(null, 'product.review', DataType::$STRING, false),
            new AttributeType(null, 'product.release_date', DataType::$DATE, false),
            new AttributeType(null, 'product.total', DataType::$STRING, false),
            new AttributeType(null, 'product.single_price', DataType::$STRING, false),
            new AttributeType(null, 'product.sku', DataType::$STRING, false),
            new AttributeType(null, 'product.quantity', DataType::$STRING, false),
            new AttributeType(null, 'product.image_url', DataType::$STRING, false),
            new AttributeType(null, 'product.url', DataType::$STRING, false),
            new AttributeType(null, 'product.categories', DataType::$STRING, false),
            new AttributeType(null, 'product.attributes', DataType::$JSON, false),
            new AttributeType(null, 'product.brand', DataType::$STRING, false),
            new AttributeType(null, 'product.color', DataType::$STRING, false),
            new AttributeType(null, 'product.weight', DataType::$STRING, false),
            new AttributeType(null, 'product.width', DataType::$STRING, false),
            new AttributeType(null, 'product.height', DataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_1', DataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_2', DataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_3', DataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_4', DataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_5', DataType::$STRING, false),
            new AttributeType(null, 'product.generic.double_1', DataType::$FLOAT, false),
            new AttributeType(null, 'product.generic.double_2', DataType::$FLOAT, false),
            new AttributeType(null, 'product.generic.double_3', DataType::$FLOAT, false),
            new AttributeType(null, 'product.generic.integer_1', DataType::$INTEGER, false),
            new AttributeType(null, 'product.generic.integer_2', DataType::$INTEGER, false),
            new AttributeType(null, 'product.generic.integer_3', DataType::$INTEGER, false),
            new AttributeType(null, 'product.generic.boolean_1', DataType::$BOOLEAN, false),
            new AttributeType(null, 'product.generic.boolean_2', DataType::$BOOLEAN, false),
            new AttributeType(null, 'product.generic.boolean_3', DataType::$BOOLEAN, false),
            new AttributeType(null, 'product.generic.date_1', DataType::$DATE, false),
            new AttributeType(null, 'product.generic.date_2', DataType::$DATE, false),
            new AttributeType(null, 'product.generic.date_3', DataType::$DATE, false),
            new AttributeType(null, 'product.generic.timestamp_1', DataType::$TIMESTAMP, false),
            new AttributeType(null, 'product.generic.timestamp_2', DataType::$TIMESTAMP, false),
            new AttributeType(null, 'product.generic.timestamp_3', DataType::$TIMESTAMP, false),
            new AttributeType(null, 'product.generic.json_1', DataType::$JSON, false),
            new AttributeType(null, 'product.generic.json_2', DataType::$JSON, false),
            new AttributeType(null, 'product.generic.json_3', DataType::$JSON, false),
            new AttributeType(null, 'shipping.address.salutation', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.firstname', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.lastname', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.phone', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.region', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.city', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.country', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.zip', DataType::$STRING, false),
            new AttributeType(null, 'shipping.address.street', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.salutation', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.firstname', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.lastname', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.phone', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.region', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.city', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.country', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.zip', DataType::$STRING, false),
            new AttributeType(null, 'billing.address.street', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.id', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.name', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.url', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.image_url', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.tracking.code', DataType::$STRING, false),
            new AttributeType(null, 'shipping.service.tracking.url', DataType::$STRING, false),
            new AttributeType(null, 'shipping.status', DataType::$STRING, false)
        );

        $attributes = $this->addGenericFields($attributes);

        return $attributes;
    }

    private function getCartTransactionType()
    {
        $attributes = array(
            new AttributeType(null, 'cart.id', DataType::$STRING, false),
            new AttributeType(null, 'cart.date', DataType::$TIMESTAMP, false),
            new AttributeType(null, 'cart.items', DataType::$JSON, false),
            new AttributeType(null, 'cart.product_ids', DataType::$STRING, false),
            new AttributeType(null, 'cart.categories', DataType::$STRING, false),
            new AttributeType(null, 'cart.brands', DataType::$STRING, false),
            new AttributeType(null, 'cart.total', DataType::$FLOAT, false),
            new AttributeType(null, 'cart.total_no_shipping', DataType::$FLOAT, false),
            new AttributeType(null, 'cart.total_tax', DataType::$FLOAT, false),
            new AttributeType(null, 'cart.total_fees', DataType::$FLOAT, false),
            new AttributeType(null, 'cart.total_refunds', DataType::$FLOAT, false),
            new AttributeType(null, 'cart.fees', DataType::$JSON, false),
            new AttributeType(null, 'cart.refunds', DataType::$JSON, false),
            new AttributeType(null, 'cart.currency', DataType::$STRING, false),
            new AttributeType(null, 'discount.code', DataType::$STRING, false),
            new AttributeType(null, 'discount.total', DataType::$STRING, false),
            new AttributeType(null, 'discount.rules', DataType::$JSON, false),
            new AttributeType(null, 'discount.rules_string', DataType::$STRING, false),
            new AttributeType(null, 'customer.salutation', DataType::$STRING, false),
            new AttributeType(null, 'customer.fullname', DataType::$STRING, false),
            new AttributeType(null, 'customer.firstname', DataType::$STRING, false),
            new AttributeType(null, 'customer.lastname', DataType::$STRING, false),
            new AttributeType(null, 'customer.id', DataType::$STRING, false),
        );

        $attributes = $this->addGenericFields($attributes);

        return $attributes;
    }

    private function addGenericFields($attributes)
    {
        array_push($attributes, new AttributeType(null, 'generic.string_1', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_2', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_3', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_4', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_5', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_6', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_7', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_8', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_9', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.string_10', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'generic.double_1', DataType::$DOUBLE, false));
        array_push($attributes, new AttributeType(null, 'generic.double_2', DataType::$DOUBLE, false));
        array_push($attributes, new AttributeType(null, 'generic.double_3', DataType::$DOUBLE, false));
        array_push($attributes, new AttributeType(null, 'generic.double_4', DataType::$DOUBLE, false));
        array_push($attributes, new AttributeType(null, 'generic.double_5', DataType::$DOUBLE, false));
        array_push($attributes, new AttributeType(null, 'generic.integer_1', DataType::$INTEGER, false));
        array_push($attributes, new AttributeType(null, 'generic.integer_2', DataType::$INTEGER, false));
        array_push($attributes, new AttributeType(null, 'generic.integer_3', DataType::$INTEGER, false));
        array_push($attributes, new AttributeType(null, 'generic.integer_4', DataType::$INTEGER, false));
        array_push($attributes, new AttributeType(null, 'generic.integer_5', DataType::$INTEGER, false));
        array_push($attributes, new AttributeType(null, 'generic.boolean_1', DataType::$BOOLEAN, false));
        array_push($attributes, new AttributeType(null, 'generic.boolean_2', DataType::$BOOLEAN, false));
        array_push($attributes, new AttributeType(null, 'generic.boolean_3', DataType::$BOOLEAN, false));
        array_push($attributes, new AttributeType(null, 'generic.boolean_4', DataType::$BOOLEAN, false));
        array_push($attributes, new AttributeType(null, 'generic.boolean_5', DataType::$BOOLEAN, false));
        array_push($attributes, new AttributeType(null, 'generic.date_1', DataType::$DATE, false));
        array_push($attributes, new AttributeType(null, 'generic.date_2', DataType::$DATE, false));
        array_push($attributes, new AttributeType(null, 'generic.date_3', DataType::$DATE, false));
        array_push(
            $attributes,
            new AttributeType(null, 'generic.timestamp_1', DataType::$TIMESTAMP, false)
        );
        array_push(
            $attributes,
            new AttributeType(null, 'generic.timestamp_2', DataType::$TIMESTAMP, false)
        );
        array_push(
            $attributes,
            new AttributeType(null, 'generic.timestamp_3', DataType::$TIMESTAMP, false)
        );
        array_push($attributes, new AttributeType(null, 'generic.json_1', DataType::$JSON, false));
        array_push($attributes, new AttributeType(null, 'generic.json_2', DataType::$JSON, false));
        array_push($attributes, new AttributeType(null, 'generic.json_3', DataType::$JSON, false));

        return $attributes;
    }

    /**
     * Send abandoned cart transaction for Maileon
     *
     */

    public function sendAbandonedCartTransaction(
        $email,
        $content,
        $permission,
        $standard_fields = array(),
        $custom_fields = array()
    ) {
        $transactionsService = new TransactionsService($this->maileon_config);
        $transactionsService->setDebug($this->print_curl);

        $existsTransactionType = $this->existsTransactionType('magento_abandoned_carts_v2');

        if (!$existsTransactionType) {
            $transactionType = $this->setTransactionType('magento_abandoned_carts_v2');

            if (!$transactionType) {
                $this->logger->error(
                    'Error in TransactionType creation!'
                );
            }
        }

        $transaction = new Transaction();
        $transaction->contact = new ContactReference();

        $sync = new ContactCreate($this->apikey, $email, $permission, 'no', 'no', null, $this->print_curl);

        if ($sync->maileonContactIsExists()) {
            $transaction->contact->email = $email;
        } else {
            $response = $sync->makeMalieonContact(null, $standard_fields, $custom_fields);

            if ($response) {
                $transaction->contact->email = $email;
                $this->logger->info('Contact subscribe Done!');
            } else {
                $this->logger->debug('Contact subscribe Failed!');
            }
        }

        $transaction->typeName = 'magento_abandoned_carts_v2';

        $transaction->content = $content;

        $result = $transactionsService->createTransactions(array($transaction), true, false);

        if (!$result->isSuccess()) {
            $this->logger->debug("Failed sending to: " . $email . ". Debug information: " . $result->getBodyData());
            return false;
        } else {
            return $result;
        }
    }

    /**
     * Process abandoned cart reminder
     *
     */

    public function processAbandonedCartReminder(
        $email,
        $content,
        $permission,
        $shadowEmail,
        $overrideEmail,
        $standard_fields = array(),
        $custom_fields = array()
    ) {
        // Check wether to set the original email or the override email address
        if (isset($shadowEmail) && !empty($shadowEmail) && trim($shadowEmail) != '') {
            $this->sendAbandonedCartTransaction(
                $shadowEmail,
                $content,
                $permission,
                $standard_fields,
                $custom_fields
            );
        }

        // Check wether to set the original email or the override email address
        if (isset($overrideEmail) && !empty($overrideEmail) && trim($overrideEmail) != '') {
            $email = $overrideEmail;
        }

        return $this->sendAbandonedCartTransaction(
            $email,
            $content,
            $permission,
            $standard_fields,
            $custom_fields
        );
    }
}
