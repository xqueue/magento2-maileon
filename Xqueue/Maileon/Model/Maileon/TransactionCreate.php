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
     *
     * @var string $apiKey
     */
    private $apiKey;

    /**
     * Print CURL debug data
     *
     * @var string $printCurl
     */
    private $printCurl;

    /**
     * Maileon config
     *
     * @var array $maileonConfig
     */
    private $maileonConfig;

    /**
     * Logger interface
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;


    /**
     * @param string $apiKey
     * @param string $printCurl
     */
    public function __construct($apiKey, $printCurl)
    {
        $this->apiKey = $apiKey;
        $this->printCurl = filter_var($printCurl, FILTER_VALIDATE_BOOLEAN);

        $this->maileonConfig = array(
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->apiKey,
            'TIMEOUT' => 35
        );

        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');
    }

    /**
     * Send transaction to Maileon
     *
     * @param string $email
     * @param string $transaction_name
     * @param array $content
     * @return void
     */
    public function sendTransaction(
        $email,
        $transaction_name,
        $content
    ) {
        $transactionsService = new TransactionsService($this->maileonConfig);
        $transactionsService->setDebug($this->printCurl);

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

    /**
     * Check transaction type is exist or not
     *
     * @param string $transaction_name
     * @return boolean
     */
    public function existsTransactionType($transaction_name)
    {
        $transactionsService = new TransactionsService($this->maileonConfig);
        $transactionsService->setDebug($this->printCurl);

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
     * Make Maileon transaction types for Magento
     *
     * @return string $transaction_name
     */
    public function setTransactionType($transaction_name)
    {
        $transactionsService = new TransactionsService($this->maileonConfig);
        $transactionsService->setDebug($this->printCurl);

        $transactionType = new TransactionType();
        $transactionType->name = $transaction_name;

        switch ($transaction_name) {
            case 'magento_orders_v2':
                $transactionType->attributes = $this->getOrderTransactionType();
                break;

            case 'magento_order_status_changed_v1':
                $transactionType->attributes = $this->getOrderStatusChangeTxType();
                break;

            case 'magento_orders_extended_v2':
                $transactionType->attributes = $this->getOrderExtendedTransactionType();
                break;

            case 'magento_abandoned_carts_v2':
                $transactionType->attributes = $this->getCartTransactionType();
                break;

            case 'magento_order_creditmemo_update_v1':
                $transactionType->attributes = $this->getCreditemoTxType();
                break;

            case 'magento_order_creditmemo_v1':
                $transactionType->attributes = $this->getCreditemoTxType();
                break;

            case 'magento_order_invoice_update_v1':
                $transactionType->attributes = $this->getInvoiceTxType();
                break;

            case 'magento_order_invoice_v1':
                $transactionType->attributes = $this->getInvoiceTxType();
                break;

            case 'magento_order_shipment_update_v1':
                $transactionType->attributes = $this->getShipmentTxType();
                break;

            case 'magento_order_shipment_v1':
                $transactionType->attributes = $this->getShipmentTxType();
                break;

            case 'magento_account_credentials_changed_v1':
                $transactionType->attributes = $this->getAccCredChangedTxType();
                break;

            case 'magento_password_reminder_v1':
                $transactionType->attributes = $this->getPasswordReminderTxType();
                break;

            case 'magento_password_reset_confirmation_v1':
                $transactionType->attributes = $this->getPasswordResetconfirmationrTxType();
                break;

            case 'magento_new_account_v1':
                $transactionType->attributes = $this->getNewAccountTxType();
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

    /**
     * Create order transaction type fields
     *
     * @return array
     */
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

    /**
     * Create order extended transaction type fields
     *
     * @return array
     */
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

    /**
     * Create order status change transaction type fields
     *
     * @return array
     */
    private function getOrderStatusChangeTxType()
    {
        $attributes = $this->getOrderTransactionType();

        array_push($attributes, new AttributeType(null, 'order.comment', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'store.id', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'store.name', DataType::$STRING, false));

        return $attributes;
    }

    /**
     * Create creditmemo transaction type fields
     *
     * @return array
     */
    private function getCreditemoTxType()
    {
        $attributes = $this->getOrderTransactionType();

        array_push($attributes, new AttributeType(null, 'order.creditmemo.id', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'order.comment', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'store.id', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'store.name', DataType::$STRING, false));

        return $attributes;
    }

    /**
     * Create invoice transaction type fields
     *
     * @return array
     */
    private function getInvoiceTxType()
    {
        $attributes = $this->getOrderTransactionType();

        array_push($attributes, new AttributeType(null, 'order.invoice.id', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'order.comment', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'store.id', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'store.name', DataType::$STRING, false));

        return $attributes;
    }

    /**
     * Create shipment transaction type fields
     *
     * @return array
     */
    private function getShipmentTxType()
    {
        $attributes = $this->getOrderTransactionType();

        array_push($attributes, new AttributeType(null, 'order.shipment.id', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'order.comment', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'store.id', DataType::$STRING, false));
        array_push($attributes, new AttributeType(null, 'store.name', DataType::$STRING, false));

        return $attributes;
    }

    /**
     * Create abandoned cart transaction type fields
     *
     * @return array
     */
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

    /**
     * Create account credentials changed transaction type fields
     *
     * @return array
     */
    private function getAccCredChangedTxType()
    {
        $attributes = array(
            new AttributeType(null, 'fullname', DataType::$STRING, false),
            new AttributeType(null, 'changed_field', DataType::$STRING, false),
            new AttributeType(null, 'store_id', DataType::$STRING, false),
            new AttributeType(null, 'store_name', DataType::$STRING, false),
            new AttributeType(null, 'store_email', DataType::$STRING, false),
            new AttributeType(null, 'store_phone', DataType::$STRING, false)
        );

        return $attributes;
    }

    /**
     * Create password reminder transaction type fields
     *
     * @return array
     */
    private function getPasswordReminderTxType()
    {
        $attributes = array(
            new AttributeType(null, 'fullname', DataType::$STRING, false),
            new AttributeType(null, 'account_url', DataType::$STRING, false),
            new AttributeType(null, 'psw_reset_url', DataType::$STRING, false),
            new AttributeType(null, 'store_id', DataType::$STRING, false),
            new AttributeType(null, 'store_name', DataType::$STRING, false)
        );

        return $attributes;
    }

    /**
     * Create password reset confirmation transaction type fields
     *
     * @return array
     */
    private function getPasswordResetconfirmationrTxType()
    {
        $attributes = array(
            new AttributeType(null, 'fullname', DataType::$STRING, false),
            new AttributeType(null, 'psw_reset_url', DataType::$STRING, false),
            new AttributeType(null, 'store_id', DataType::$STRING, false),
            new AttributeType(null, 'store_name', DataType::$STRING, false)
        );

        return $attributes;
    }

    /**
     * Create new account transaction type fields
     *
     * @return array
     */
    private function getNewAccountTxType()
    {
        $attributes = array(
            new AttributeType(null, 'fullname', DataType::$STRING, false),
            new AttributeType(null, 'type', DataType::$STRING, false),
            new AttributeType(null, 'account_url', DataType::$STRING, false),
            new AttributeType(null, 'account_confirm_url', DataType::$STRING, false),
            new AttributeType(null, 'psw_reset_url', DataType::$STRING, false),
            new AttributeType(null, 'store_id', DataType::$STRING, false),
            new AttributeType(null, 'store_name', DataType::$STRING, false)
        );

        return $attributes;
    }

    /**
     * Add generic fields to the transaction
     *
     * @param array $attributes
     * @return array
     */
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
     * @param string $email
     * @param array $content
     * @param integer $permission
     * @param array $standard_fields
     * @param array $custom_fields
     * @return boolean
     */
    public function sendAbandonedCartTransaction(
        $email,
        $content,
        $permission,
        $standard_fields = array(),
        $custom_fields = array()
    ) {
        $transactionsService = new TransactionsService($this->maileonConfig);
        $transactionsService->setDebug($this->printCurl);

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

        $sync = new ContactCreate($this->apiKey, $email, $permission, 'no', 'no', null, $this->printCurl);

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
        }

        return $result->isSuccess();
    }

    /**
     * Process abandoned cart reminder
     *
     * @param string $email
     * @param array $content
     * @param integer $permission
     * @param string $shadowEmail
     * @param string $overrideEmail
     * @param array $standard_fields
     * @param array $custom_fields
     * @return void
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
