<?php

/**
 * Createe Maileon Transaction
 */

namespace Xqueue\Maileon\Model\Maileon;

use de\xqueue\maileon\api\client\transactions\TransactionsDataType;
use de\xqueue\maileon\api\client\transactions\TransactionsService;
use de\xqueue\maileon\api\client\transactions\TransactionType;
use de\xqueue\maileon\api\client\transactions\AttributeType;
use de\xqueue\maileon\api\client\transactions\Transaction;
use de\xqueue\maileon\api\client\transactions\ContactReference;
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
     * @param string $apikey   Maileon API key
     */
    public function __construct($apikey, $print_curl)
    {
        $this->apikey = $apikey;
        $this->print_curl = $print_curl;
    }


    /**
     * Make Maileon transaction type for Magento
     *
     * @return object TransactionType
     */

    public function setTransactionType()
    {

        $maileon_config = array(
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->apikey,
            'TIMEOUT' => 15
        );

        $transactionsService = new TransactionsService($maileon_config);

        if ($this->print_curl == 'yes') {
            $transactionsService->setDebug(true);
        } else {
            $transactionsService->setDebug(false);
        }

        $transactionType = new TransactionType();
        $transactionType->name = 'magento_orders_v2';

        $transactionType->attributes = array(
            new AttributeType(null, 'order.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.date', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'order.status', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.estimated_delivery_time', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.estimated_delivery_date', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'order.product_ids', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.categories', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.brands', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.total', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.total_no_shipping', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.total_tax', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.total_fees', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.total_refunds', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.fees', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'order.refunds', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'order.currency', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.name', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.image_url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.data', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.due_date', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'payment.status', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.code', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.total', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.rules', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'discount.rules_string', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.salutation', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.fullname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.firstname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.lastname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.items', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'shipping.address.salutation', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.firstname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.lastname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.phone', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.region', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.city', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.country', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.zip', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.street', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.salutation', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.firstname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.lastname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.phone', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.region', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.city', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.country', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.zip', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.street', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.name', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.image_url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.tracking.code', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.tracking.url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.status', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_1', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_2', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_3', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_4', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_5', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_6', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_7', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_8', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_9', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_10', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.double_1', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_2', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_3', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_4', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_5', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.integer_1', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_2', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_3', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_4', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_5', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.boolean_1', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_2', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_3', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_4', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_5', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.date_1', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.date_2', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.date_3', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.timestamp_1', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.timestamp_2', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.timestamp_3', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.json_1', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'generic.json_2', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'generic.json_3', TransactionsDataType::$JSON, false)
        );

        $transactionsService->createTransactionType($transactionType);

        $existsTransactionType = $transactionsService->getTransactionTypeByName('magento_orders_v2');

        if ($existsTransactionType->getStatusCode() === 404) {
            return false;
        } else {
            return $existsTransactionType;
        }
    }


    /**
     * Make Maileon transaction type for Magento extended datas
     *
     * @return object TransactionType
     */

    public function setTransactionTypeExtended()
    {

        $maileon_config = array(
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->apikey,
            'TIMEOUT' => 15
        );

        $transactionsService = new TransactionsService($maileon_config);

        if ($this->print_curl == 'yes') {
            $transactionsService->setDebug(true);
        } else {
            $transactionsService->setDebug(false);
        }

        $transactionType = new TransactionType();
        $transactionType->name = 'magento_orders_extended_v2';

        $transactionType->attributes = array(
            new AttributeType(null, 'order.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.date', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'order.status', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.estimated_delivery_time', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.estimated_delivery_date', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'order.product_ids', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.categories', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.brands', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'order.total', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.total_no_shipping', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.total_tax', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.total_fees', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.total_refunds', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'order.fees', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'order.refunds', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'order.currency', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.name', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.image_url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.method.data', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'payment.due_date', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'payment.status', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.code', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.total', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.rules', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'discount.rules_string', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.salutation', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.fullname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.firstname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.lastname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.title', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.description', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.short_description', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.review', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.release_date', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'product.total', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.single_price', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.sku', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.quantity', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.image_url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.categories', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.attributes', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'product.brand', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.color', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.weight', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.width', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.height', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_1', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_2', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_3', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_4', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.generic.string_5', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'product.generic.double_1', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'product.generic.double_2', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'product.generic.double_3', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'product.generic.integer_1', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'product.generic.integer_2', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'product.generic.integer_3', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'product.generic.boolean_1', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'product.generic.boolean_2', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'product.generic.boolean_3', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'product.generic.date_1', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'product.generic.date_2', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'product.generic.date_3', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'product.generic.timestamp_1', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'product.generic.timestamp_2', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'product.generic.timestamp_3', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'product.generic.json_1', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'product.generic.json_2', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'product.generic.json_3', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'shipping.address.salutation', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.firstname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.lastname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.phone', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.region', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.city', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.country', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.zip', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.address.street', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.salutation', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.firstname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.lastname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.phone', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.region', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.city', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.country', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.zip', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'billing.address.street', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.name', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.image_url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.tracking.code', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.service.tracking.url', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'shipping.status', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_1', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_2', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_3', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_4', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_5', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_6', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_7', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_8', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_9', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_10', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.double_1', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_2', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_3', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_4', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_5', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.integer_1', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_2', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_3', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_4', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_5', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.boolean_1', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_2', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_3', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_4', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_5', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.date_1', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.date_2', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.date_3', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.timestamp_1', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.timestamp_2', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.timestamp_3', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.json_1', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'generic.json_2', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'generic.json_3', TransactionsDataType::$JSON, false)
        );

        $transactionsService->createTransactionType($transactionType);

        $existsTransactionType = $transactionsService->getTransactionTypeByName('magento_orders_extended_v2');

        if ($existsTransactionType->getStatusCode() === 404) {
            return false;
        } else {
            return $existsTransactionType;
        }
    }

    /**
     * Make Maileon transaction type for Magento abandoned carts
     *
     * @return object TransactionType
     */

    public function setTransactionTypeAbandoned()
    {

        $maileon_config = array(
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->apikey,
            'TIMEOUT' => 15
        );

        $transactionsService = new TransactionsService($maileon_config);

        if ($this->print_curl == 'yes') {
            $transactionsService->setDebug(true);
        } else {
            $transactionsService->setDebug(false);
        }

        $transactionType = new TransactionType();
        $transactionType->name = 'magento_abandoned_carts_v2';

        $transactionType->attributes = array(
            new AttributeType(null, 'cart.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'cart.date', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'cart.items', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'cart.product_ids', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'cart.categories', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'cart.brands', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'cart.total', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'cart.total_no_shipping', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'cart.total_tax', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'cart.total_fees', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'cart.total_refunds', TransactionsDataType::$FLOAT, false),
            new AttributeType(null, 'cart.fees', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'cart.refunds', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'cart.currency', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.code', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.total', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'discount.rules', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'discount.rules_string', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.salutation', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.fullname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.firstname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.lastname', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'customer.id', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_1', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_2', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_3', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_4', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_5', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_6', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_7', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_8', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_9', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.string_10', TransactionsDataType::$STRING, false),
            new AttributeType(null, 'generic.double_1', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_2', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_3', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_4', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.double_5', TransactionsDataType::$DOUBLE, false),
            new AttributeType(null, 'generic.integer_1', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_2', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_3', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_4', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.integer_5', TransactionsDataType::$INTEGER, false),
            new AttributeType(null, 'generic.boolean_1', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_2', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_3', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_4', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.boolean_5', TransactionsDataType::$BOOLEAN, false),
            new AttributeType(null, 'generic.date_1', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.date_2', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.date_3', TransactionsDataType::$DATE, false),
            new AttributeType(null, 'generic.timestamp_1', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.timestamp_2', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.timestamp_3', TransactionsDataType::$TIMESTAMP, false),
            new AttributeType(null, 'generic.json_1', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'generic.json_2', TransactionsDataType::$JSON, false),
            new AttributeType(null, 'generic.json_3', TransactionsDataType::$JSON, false)
        );

        $transactionsService->createTransactionType($transactionType);

        $existsTransactionType = $transactionsService->getTransactionTypeByName('magento_abandoned_carts_v2');

        if ($existsTransactionType->getStatusCode() === 404) {
            return false;
        } else {
            return $existsTransactionType;
        }
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

        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');

        $maileon_config = array(
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->apikey,
            'TIMEOUT' => 15
        );

        $transactionsService = new TransactionsService($maileon_config);

        if ($this->print_curl == 'yes') {
            $transactionsService->setDebug(true);
        } else {
            $transactionsService->setDebug(false);
        }

        $existsTransactionType = $transactionsService->getTransactionTypeByName('magento_abandoned_carts_v2');

        if ($existsTransactionType->getStatusCode() === 404) {
            $existsTransactionType = $this->setTransactionTypeAbandoned();
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
                $logger->info('Contact subscribe Done!');
            } else {
                $logger->debug('Contact subscribe Failed!');
            }
        }

        $transaction->typeName = 'magento_abandoned_carts_v2';

        $transaction->content = $content;

        $result = $transactionsService->createTransactions(array($transaction), true, false);

        if (!$result->isSuccess()) {
            $logger->debug("Failed sending to: " . $email . ". Debug information: " . $result->getBodyData());
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
