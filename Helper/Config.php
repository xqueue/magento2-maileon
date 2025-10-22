<?php

namespace Xqueue\Maileon\Helper;

use Magento\Directory\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_API_KEY = 'xqueue_maileon/general/api_key';
    public const XML_PATH_NL_ENABLED = 'xqueue_maileon/newsletter_settings/active_modul';
    public const XML_PATH_NL_PERMISSION = 'xqueue_maileon/newsletter_settings/permission';
    public const XML_PATH_NL_DOI_PROCESS = 'xqueue_maileon/newsletter_settings/doi_process';
    public const XML_PATH_NL_DOI_PLUS = 'xqueue_maileon/newsletter_settings/doi_plus_process';
    public const XML_PATH_NL_DOI_KEY = 'xqueue_maileon/newsletter_settings/doi_key';
    public const XML_PATH_NL_UNSUB_TOKEN = 'xqueue_maileon/newsletter_settings/unsubscribe_token';
    public const XML_PATH_NL_DOI_TOKEN = 'xqueue_maileon/newsletter_settings/doi_token';
    public const XML_PATH_NL_UNSUB_ALL = 'xqueue_maileon/newsletter_settings/unsubscribe_all_emails';
    public const XML_PATH_NL_DISABLE_CONFIRM_EMAIL = 'xqueue_maileon/newsletter_settings/disable_request_email';
    public const XML_PATH_NL_DISABLE_SUCCESS_EMAIL = 'xqueue_maileon/newsletter_settings/disable_success_email';
    public const XML_PATH_NL_DISABLE_UNSUB_EMAIL = 'xqueue_maileon/newsletter_settings/disable_unsubscription_email';
    public const XML_PATH_ORD_CONF_ENABLED = 'xqueue_maileon/orders/active_modul';
    public const XML_PATH_ORD_CONF_DISABLE_ORDER_EMAIL = 'xqueue_maileon/orders/disable_order_emails';
    public const XML_PATH_ORD_CONF_BUYERS_PERM_ENABLED = 'xqueue_maileon/orders/buyers_permission_enabled';
    public const XML_PATH_ORD_CONF_BUYERS_TX_PERM = 'xqueue_maileon/orders/buyers_transaction_permission';
    public const XML_PATH_CUSTOMER_TX_CRED_CHANGED = 'xqueue_maileon/customer_related_transactions/credentials_changed';
    public const XML_PATH_DISABLE_CRED_CHANGED_EMAIL = 'xqueue_maileon/customer_related_transactions/disable_credential_changed_email';
    public const XML_PATH_CUSTOMER_TX_PSW_REMINDER = 'xqueue_maileon/customer_related_transactions/password_reminder';
    public const XML_PATH_DISABLE_PSW_REMINDER_EMAIL = 'xqueue_maileon/customer_related_transactions/disable_password_reminder_email';
    public const XML_PATH_CUSTOMER_TX_PSW_RESET = 'xqueue_maileon/customer_related_transactions/password_reset_confirm';
    public const XML_PATH_DISABLE_PSW_RESET_EMAIL = 'xqueue_maileon/customer_related_transactions/disable_password_reset_email';
    public const XML_PATH_CUSTOMER_TX_NEW_ACCOUNT = 'xqueue_maileon/customer_related_transactions/new_account';
    public const XML_PATH_DISABLE_NEW_ACCOUNT_EMAIL = 'xqueue_maileon/customer_related_transactions/disable_new_account_email';
    public const XML_PATH_PRODUCT_ADDED_TO_WISHLIST = 'xqueue_maileon/customer_related_transactions/wishlist_product_added';
    public const XML_PATH_ORD_TX_CREDIT_MEMO = 'xqueue_maileon/order_related_transactions/credit_memo';
    public const XML_PATH_DISABLE_CREDIT_MEMO_EMAIL = 'xqueue_maileon/order_related_transactions/disable_credit_memo_email';
    public const XML_PATH_ORD_TX_INVOICE = 'xqueue_maileon/order_related_transactions/invoice';
    public const XML_PATH_DISABLE_INVOICE_EMAIL = 'xqueue_maileon/order_related_transactions/disable_invoice_email';
    public const XML_PATH_ORD_TX_ORDER_STATUS = 'xqueue_maileon/order_related_transactions/order_status';
    public const XML_PATH_DISABLE_ORDER_STATUS_EMAIL = 'xqueue_maileon/order_related_transactions/disable_order_status_email';
    public const XML_PATH_ORD_TX_SHIPMENT_STATUS = 'xqueue_maileon/order_related_transactions/shipment_status';
    public const XML_PATH_DISABLE_SHIPMENT_STATUS_EMAIL = 'xqueue_maileon/order_related_transactions/disable_shipment_status_email';
    public const XML_PATH_ABAN_CART_ENABLED = 'xqueue_maileon/abandoned_cart/active_modul';
    public const XML_PATH_ABAN_CART_PERMISSION = 'xqueue_maileon/abandoned_cart/permission';
    public const XML_PATH_ABAN_CART_HOURS = 'xqueue_maileon/abandoned_cart/hours';
    public const XML_PATH_ABAN_CART_SHADOW_EMAIL = 'xqueue_maileon/abandoned_cart/shadow_email';
    public const XML_PATH_ABAN_CART_EMAIL_OVERRIDE = 'xqueue_maileon/abandoned_cart/email_override';
    public const XML_PATH_NL_IMPORT_ENABLED = 'xqueue_maileon/nl_subscribers_import/enabled';
    public const XML_PATH_NL_IMPORT_PERMISSION = 'xqueue_maileon/nl_subscribers_import/permission';
    public const XML_PATH_ORDER_HISTORY_IMPORT_ENABLED = 'xqueue_maileon/order_history_import/enabled';

    public const ORDER_CONFIRM_TX_NAME = 'magento_orders_v2';
    public const ORDERED_PRODUCTS_TX_NAME = 'magento_orders_extended_v2';
    public const ORDER_STATUS_CHANGED_TX_NAME = 'magento_order_status_changed_v1';
    public const ABANDONED_CARTS_TX_NAME = 'magento_abandoned_carts_v2';
    public const CREDIT_MEMO_TX_NAME = 'magento_order_creditmemo_v1';
    public const CREDIT_MEMO_UPDATED_TX_NAME = 'magento_order_creditmemo_update_v1';
    public const ORDER_INVOICE_TX_NAME = 'magento_order_invoice_v1';
    public const ORDER_INVOICE_UPDATED_TX_NAME = 'magento_order_invoice_update_v1';
    public const ORDER_SHIPMENT_TX_NAME = 'magento_order_shipment_v1';
    public const ORDER_SHIPMENT_UPDATED_TX_NAME = 'magento_order_shipment_update_v1';
    public const ACCOUNT_CREDENTIALS_CHANGED_TX_NAME = 'magento_account_credentials_changed_v1';
    public const PASSWORD_REMINDER_TX_NAME = 'magento_password_reminder_v1';
    public const PASSWORD_RESET_TX_NAME = 'magento_password_reset_confirmation_v1';
    public const NEW_ACCOUNT_TX_NAME = 'magento_new_account_v1';
    public const WISHLIST_PRODUCT_ADDED_TX_NAME = 'magento_wishlist_added_v1';

    public const XSIC_ID = '10017';
    public const XSIC_CHECKSUM = 'L9_Z6734NbgB_xk7D23hRJZs';
    public const XSIC_URL = 'https://integrations.maileon.com/xsic/tx.php';

    public const STANDARD_FIELDS = [
        'FIRSTNAME'    => 'string',
        'LASTNAME'     => 'string',
        'FULLNAME'     => 'string',
        'GENDER'       => 'enum', // m, f, d
        'BIRTHDAY'     => 'date',
        'NAMEDAY'      => 'date',
        'SALUTATION'   => 'string',
        'TITLE'        => 'string',
        'ADDRESS'      => 'string',
        'ZIP'          => 'string',
        'HNR'          => 'string',
        'CITY'         => 'string',
        'COUNTRY'      => 'string',
        'STATE'        => 'string',
        'REGION'       => 'string',
        'ORGANIZATION' => 'string',
    ];

    public const ALLOWED_GENDERS = ['m', 'f', 'd'];

    public const CUSTOM_FIELDS = [
        'Magento_NL' => 'boolean',
        'magento_created' => 'boolean',
        'createdByTransaction' => 'boolean',
        'magento_phone' => 'string',
        'magento_storeview_id' => 'string',
        'magento_source' => 'string',
        'magento_domain' => 'string',
    ];

    public const ORDER_CONFIRM_TX_TYPE_FIELDS = [
        'order.id' => 'string',
        'order.date' => 'timestamp',
        'order.status' => 'string',
        'order.estimated_delivery_time' => 'string',
        'order.estimated_delivery_date' => 'date',
        'order.product_ids' => 'string',
        'order.categories' => 'string',
        'order.brands' => 'string',
        'order.total' => 'float',
        'order.total_no_shipping' => 'float',
        'order.total_tax' => 'float',
        'order.total_fees' => 'float',
        'order.total_refunds' => 'float',
        'order.fees' => 'json',
        'order.refunds' => 'json',
        'order.currency' => 'string',
        'payment.method.id' => 'string',
        'payment.method.name' => 'string',
        'payment.method.url' => 'string',
        'payment.method.image_url' => 'string',
        'payment.method.data' => 'string',
        'payment.due_date' => 'date',
        'payment.status' => 'string',
        'discount.code' => 'string',
        'discount.total' => 'string',
        'discount.rules' => 'json',
        'discount.rules_string' => 'string',
        'customer.salutation' => 'string',
        'customer.fullname' => 'string',
        'customer.firstname' => 'string',
        'customer.lastname' => 'string',
        'customer.id' => 'string',
        'order.items' => 'json',
        'shipping.address.salutation' => 'string',
        'shipping.address.firstname' => 'string',
        'shipping.address.lastname' => 'string',
        'shipping.address.phone' => 'string',
        'shipping.address.region' => 'string',
        'shipping.address.city' => 'string',
        'shipping.address.country' => 'string',
        'shipping.address.zip' => 'string',
        'shipping.address.street' => 'string',
        'billing.address.salutation' => 'string',
        'billing.address.firstname' => 'string',
        'billing.address.lastname' => 'string',
        'billing.address.phone' => 'string',
        'billing.address.region' => 'string',
        'billing.address.city' => 'string',
        'billing.address.country' => 'string',
        'billing.address.zip' => 'string',
        'billing.address.street' => 'string',
        'shipping.service.id' => 'string',
        'shipping.service.name' => 'string',
        'shipping.service.url' => 'string',
        'shipping.service.image_url' => 'string',
        'shipping.service.tracking.code' => 'string',
        'shipping.service.tracking.url' => 'string',
        'shipping.status' => 'string',
    ];

    public const ORDERED_PRODUCTS_TX_FIELDS = [
        'order.id' => 'string',
        'order.date' => 'timestamp',
        'order.status' => 'string',
        'order.estimated_delivery_time' => 'string',
        'order.estimated_delivery_date' => 'date',
        'order.product_ids' => 'string',
        'order.categories' => 'string',
        'order.brands' => 'string',
        'order.total' => 'float',
        'order.total_no_shipping' => 'float',
        'order.total_tax' => 'float',
        'order.total_fees' => 'float',
        'order.total_refunds' => 'float',
        'order.fees' => 'json',
        'order.refunds' => 'json',
        'order.currency' => 'string',
        'payment.method.id' => 'string',
        'payment.method.name' => 'string',
        'payment.method.url' => 'string',
        'payment.method.image_url' => 'string',
        'payment.method.data' => 'string',
        'payment.due_date' => 'date',
        'payment.status' => 'string',
        'discount.code' => 'string',
        'discount.total' => 'string',
        'discount.rules' => 'json',
        'discount.rules_string' => 'string',
        'customer.salutation' => 'string',
        'customer.fullname' => 'string',
        'customer.firstname' => 'string',
        'customer.lastname' => 'string',
        'customer.id' => 'string',
        'product.id' => 'string',
        'product.title' => 'string',
        'product.description' => 'string',
        'product.short_description' => 'string',
        'product.review' => 'string',
        'product.release_date' => 'date',
        'product.total' => 'string',
        'product.single_price' => 'string',
        'product.sku' => 'string',
        'product.quantity' => 'string',
        'product.image_url' => 'string',
        'product.url' => 'string',
        'product.categories' => 'string',
        'product.attributes' => 'json',
        'product.brand' => 'string',
        'product.color' => 'string',
        'product.weight' => 'string',
        'product.width' => 'string',
        'product.height' => 'string',
        'product.generic.string_1' => 'string',
        'product.generic.string_3' => 'string',
        'product.generic.string_4' => 'string',
        'product.generic.string_5' => 'string',
        'product.generic.double_1' => 'float',
        'product.generic.double_2' => 'float',
        'product.generic.double_3' => 'float',
        'product.generic.integer_1' => 'integer',
        'product.generic.integer_2' => 'integer',
        'product.generic.integer_3' => 'integer',
        'product.generic.boolean_1' => 'boolean',
        'product.generic.boolean_2' => 'boolean',
        'product.generic.boolean_3' => 'boolean',
        'product.generic.date_1' => 'date',
        'product.generic.date_2' => 'date',
        'product.generic.date_3' => 'date',
        'product.generic.timestamp_1' => 'timestamp',
        'product.generic.timestamp_2' => 'timestamp',
        'product.generic.timestamp_3' => 'timestamp',
        'product.generic.json_1' => 'json',
        'product.generic.json_2' => 'json',
        'product.generic.json_3' => 'json',
        'shipping.address.salutation' => 'string',
        'shipping.address.firstname' => 'string',
        'shipping.address.lastname' => 'string',
        'shipping.address.phone' => 'string',
        'shipping.address.region' => 'string',
        'shipping.address.city' => 'string',
        'shipping.address.country' => 'string',
        'shipping.address.zip' => 'string',
        'shipping.address.street' => 'string',
        'billing.address.salutation' => 'string',
        'billing.address.firstname' => 'string',
        'billing.address.lastname' => 'string',
        'billing.address.phone' => 'string',
        'billing.address.region' => 'string',
        'billing.address.city' => 'string',
        'billing.address.country' => 'string',
        'billing.address.zip' => 'string',
        'billing.address.street' => 'string',
        'shipping.service.id' => 'string',
        'shipping.service.name' => 'string',
        'shipping.service.url' => 'string',
        'shipping.service.image_url' => 'string',
        'shipping.service.tracking.code' => 'string',
        'shipping.service.tracking.url' => 'string',
        'shipping.status' => 'string',
    ];

    public const ORDER_STATUS_CHANGE_TX_TYPE_FIELDS = [
        'order.comment' => 'string',
        'store.id' => 'string',
        'store.name' => 'string',
    ];

    public const CREDIT_MEMO_TX_TYPE_FIELDS = [
        'order.creditmemo.id' => 'string',
        'order.comment' => 'string',
        'store.id' => 'string',
        'store.name' => 'string',
    ];

    public const INVOICE_TX_TYPE_FIELDS = [
        'order.invoice.id' => 'string',
        'order.comment' => 'string',
        'store.id' => 'string',
        'store.name' => 'string',
    ];

    public const SHIPMENT_TX_TYPE_FIELDS = [
        'order.shipment.id' => 'string',
        'order.comment' => 'string',
        'store.id' => 'string',
        'store.name' => 'string',
    ];

    public const ABANDONED_CART_TX_TYPE_FIELDS = [
        'cart.id' => 'string',
        'cart.date' => 'timestamp',
        'cart.items' => 'json',
        'cart.product_ids' => 'string',
        'cart.categories' => 'string',
        'cart.brands' => 'string',
        'cart.total' => 'float',
        'cart.total_no_shipping' => 'float',
        'cart.total_tax' => 'float',
        'cart.total_fees' => 'float',
        'cart.total_refunds' => 'float',
        'cart.fees' => 'json',
        'cart.refunds' => 'json',
        'cart.currency' => 'string',
        'discount.code' => 'string',
        'discount.total' => 'string',
        'discount.rules' => 'json',
        'discount.rules_string' => 'string',
        'customer.salutation' => 'string',
        'customer.fullname' => 'string',
        'customer.firstname' => 'string',
        'customer.lastname' => 'string',
        'customer.id' => 'string',
    ];

    public const ACCOUNT_CREDENTIAL_CHANGED_TX_TYPE_FIELDS = [
        'fullname' => 'string',
        'changed_field' => 'string',
        'store_id' => 'string',
        'store_name' => 'string',
        'store_email' => 'string',
        'store_phone' => 'string',
    ];

    public const PASSWORD_REMINDER_TX_TYPE_FIELDS = [
        'fullname' => 'string',
        'account_url' => 'string',
        'psw_reset_url' => 'string',
        'store_id' => 'string',
        'store_name' => 'string',
    ];

    public const PASSWORD_RESET_TX_TYPE_FIELDS = [
        'fullname' => 'string',
        'psw_reset_url' => 'string',
        'store_id' => 'string',
        'store_name' => 'string',
    ];

    public const NEW_ACCOUNT_TX_TYPE_FIELDS = [
        'fullname' => 'string',
        'type' => 'string',
        'account_url' => 'string',
        'account_confirm_url' => 'string',
        'psw_reset_url' => 'string',
        'store_id' => 'string',
        'store_name' => 'string',
    ];

    public const WISHLIST_PRODUCT_ADDED_TX_FIELDS = [
        'customer.id' => 'string',
        'customer.salutation' => 'string',
        'customer.fullname' => 'string',
        'customer.firstname' => 'string',
        'customer.lastname' => 'string',
        'customer.address.street' => 'string',
        'customer.address.hnr' => 'string',
        'customer.address.zip' => 'string',
        'customer.address.city' => 'string',
        'customer.address.state' => 'string',
        'customer.address.country' => 'string',
        'product.sku' => 'string',
        'product.id' => 'string',
        'product.title' => 'string',
        'product.description' => 'string',
        'product.url' => 'string',
        'product.image_url' => 'string',
        'product.available_stock' => 'integer',
        'product.gross_price' => 'float',
        'product.net_price' => 'float',
        'product.currency_name' => 'string',
        'product.currency_symbol' => 'string',
        'product.categories' => 'string',
        'product.brand' => 'string',
        'product.unit' => 'string',
        'product.weight' => 'string',
        'product.width' => 'string',
        'product.height' => 'string',
        'store_id' => 'string',
        'store_name' => 'string',
    ];

    public const GENERIC_FIELDS = [
        'generic.string_1' => 'string',
        'generic.string_2' => 'string',
        'generic.string_3' => 'string',
        'generic.string_4' => 'string',
        'generic.string_5' => 'string',
        'generic.string_6' => 'string',
        'generic.string_7' => 'string',
        'generic.string_8' => 'string',
        'generic.string_9' => 'string',
        'generic.string_10' => 'string',
        'generic.double_1' => 'double',
        'generic.double_2' => 'double',
        'generic.double_3' => 'double',
        'generic.double_4' => 'double',
        'generic.double_5' => 'double',
        'generic.integer_1' => 'integer',
        'generic.integer_2' => 'integer',
        'generic.integer_3' => 'integer',
        'generic.integer_4' => 'integer',
        'generic.integer_5' => 'integer',
        'generic.boolean_1' => 'boolean',
        'generic.boolean_2' => 'boolean',
        'generic.boolean_3' => 'boolean',
        'generic.boolean_4' => 'boolean',
        'generic.boolean_5' => 'boolean',
        'generic.date_1' => 'date',
        'generic.date_2' => 'date',
        'generic.date_3' => 'date',
        'generic.timestamp_1' => 'timestamp',
        'generic.timestamp_2' => 'timestamp',
        'generic.timestamp_3' => 'timestamp',
        'generic.json_1' => 'json',
        'generic.json_2' => 'json',
        'generic.json_3' => 'json',
    ];

    public const TRANSACTION_TYPES = [
        self::ORDER_CONFIRM_TX_NAME => [
            'fields' => self::ORDER_CONFIRM_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::ORDERED_PRODUCTS_TX_NAME => [
            'fields' => self::ORDERED_PRODUCTS_TX_FIELDS,
            'addGeneric' => true
        ],
        self::ABANDONED_CARTS_TX_NAME => [
            'fields' => self::ABANDONED_CART_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::ORDER_STATUS_CHANGED_TX_NAME => [
            'fields' => self::ORDER_CONFIRM_TX_TYPE_FIELDS,
            'extra_fields' => self::ORDER_STATUS_CHANGE_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::CREDIT_MEMO_TX_NAME => [
            'fields' => self::ORDER_CONFIRM_TX_TYPE_FIELDS,
            'extra_fields' => self::CREDIT_MEMO_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::CREDIT_MEMO_UPDATED_TX_NAME => [
            'fields' => self::ORDER_CONFIRM_TX_TYPE_FIELDS,
            'extra_fields' => self::CREDIT_MEMO_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::ORDER_INVOICE_TX_NAME => [
            'fields' => self::ORDER_CONFIRM_TX_TYPE_FIELDS,
            'extra_fields' => self::INVOICE_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::ORDER_INVOICE_UPDATED_TX_NAME => [
            'fields' => self::ORDER_CONFIRM_TX_TYPE_FIELDS,
            'extra_fields' => self::INVOICE_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::ORDER_SHIPMENT_TX_NAME => [
            'fields' => self::ORDER_CONFIRM_TX_TYPE_FIELDS,
            'extra_fields' => self::SHIPMENT_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::ORDER_SHIPMENT_UPDATED_TX_NAME => [
            'fields' => self::ORDER_CONFIRM_TX_TYPE_FIELDS,
            'extra_fields' => self::SHIPMENT_TX_TYPE_FIELDS,
            'addGeneric' => true
        ],
        self::ACCOUNT_CREDENTIALS_CHANGED_TX_NAME => [
            'fields' => self::ACCOUNT_CREDENTIAL_CHANGED_TX_TYPE_FIELDS,
            'addGeneric' => false
        ],
        self::PASSWORD_REMINDER_TX_NAME => [
            'fields' => self::PASSWORD_REMINDER_TX_TYPE_FIELDS,
            'addGeneric' => false
        ],
        self::PASSWORD_RESET_TX_NAME => [
            'fields' => self::PASSWORD_RESET_TX_TYPE_FIELDS,
            'addGeneric' => false
        ],
        self::NEW_ACCOUNT_TX_NAME => [
            'fields' => self::NEW_ACCOUNT_TX_TYPE_FIELDS,
            'addGeneric' => false
        ],
        self::WISHLIST_PRODUCT_ADDED_TX_NAME => [
            'fields' => self::WISHLIST_PRODUCT_ADDED_TX_FIELDS,
            'addGeneric' => true
        ],
    ];

    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {}

    public function getApiKey(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_API_KEY, $storeId);
    }

    public function isNewsletterModulEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_NL_ENABLED, $storeId);
    }

    public function getNlPermission(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_NL_PERMISSION, $storeId);
    }

    public function isDOIProcessEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_NL_DOI_PROCESS, $storeId);
    }

    public function isDOIPlusProcessEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_NL_DOI_PLUS, $storeId);
    }

    public function getNlDOIKey(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_NL_DOI_KEY, $storeId);
    }

    public function getDOIWebhookToken(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_NL_DOI_TOKEN, $storeId);
    }

    public function getUnsubscribeWebhookToken(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_NL_UNSUB_TOKEN, $storeId);
    }

    public function isUnsubscribeAllEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_NL_UNSUB_ALL, $storeId);
    }

    public function isOrderConfirmationSendEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_ORD_CONF_ENABLED, $storeId);
    }

    public function isBuyersPermissionEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_ORD_CONF_BUYERS_PERM_ENABLED, $storeId);
    }

    public function getBuyersPermission(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_ORD_CONF_BUYERS_TX_PERM, $storeId);
    }

    public function isCredentialsChangedTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_CUSTOMER_TX_CRED_CHANGED, $storeId);
    }

    public function isPswReminderTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_CUSTOMER_TX_PSW_REMINDER, $storeId);
    }

    public function isPswResetTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_CUSTOMER_TX_PSW_RESET, $storeId);
    }

    public function isNewAccountTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_CUSTOMER_TX_NEW_ACCOUNT, $storeId);
    }

    public function isWishlistProductAddTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_PRODUCT_ADDED_TO_WISHLIST, $storeId);
    }

    public function isCreditMemoTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_ORD_TX_CREDIT_MEMO, $storeId);
    }

    public function isInvoiceTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_ORD_TX_INVOICE, $storeId);
    }

    public function isOrderStatusTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_ORD_TX_ORDER_STATUS, $storeId);
    }

    public function isShipmentStatusTXEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_ORD_TX_SHIPMENT_STATUS, $storeId);
    }

    public function isAbandonedCartEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_ABAN_CART_ENABLED, $storeId);
    }

    public function getAbandonedCartPermission(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_ABAN_CART_PERMISSION, $storeId);
    }

    public function getAbandonedCartHours(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ABAN_CART_HOURS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function getAbandonedCartShadowEmail(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_ABAN_CART_SHADOW_EMAIL, $storeId);
    }

    public function getAbandonedCartOverrideEmail(?int $storeId = null): ?string
    {
        return $this->getValue(self::XML_PATH_ABAN_CART_EMAIL_OVERRIDE, $storeId);
    }

    public function isNewsletterSubscriberImportEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_NL_IMPORT_ENABLED,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function getNewsletterSubscriberImportPermission(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_NL_IMPORT_PERMISSION,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function isOrderHistoryImportEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ORDER_HISTORY_IMPORT_ENABLED,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function getStoreEmail(?int $storeId = null): ?string
    {
        return $this->getValue('trans_email/ident_support/email', $storeId);
    }

    public function getStorePhone(?int $storeId = null): ?string
    {
        return $this->getValue('general/store_information/phone', $storeId);
    }

    public function isOverrideEnabled(string $type): bool
    {
        $map = [
            'order_confirm' => self::XML_PATH_ORD_CONF_DISABLE_ORDER_EMAIL,
            'credit_memo' => self::XML_PATH_DISABLE_CREDIT_MEMO_EMAIL,
            'invoice' => self::XML_PATH_DISABLE_INVOICE_EMAIL,
            'order_status' => self::XML_PATH_DISABLE_ORDER_STATUS_EMAIL,
            'shipment' => self::XML_PATH_DISABLE_SHIPMENT_STATUS_EMAIL,
            'nl_confirm' => self::XML_PATH_NL_DISABLE_CONFIRM_EMAIL,
            'nl_success' => self::XML_PATH_NL_DISABLE_SUCCESS_EMAIL,
            'nl_unsubscribe' => self::XML_PATH_NL_DISABLE_UNSUB_EMAIL,
            'credentials_changed' => self::XML_PATH_DISABLE_CRED_CHANGED_EMAIL,
            'psw_reminder' => self::XML_PATH_DISABLE_PSW_REMINDER_EMAIL,
            'psw_reset' => self::XML_PATH_DISABLE_PSW_RESET_EMAIL,
            'new_account' => self::XML_PATH_DISABLE_NEW_ACCOUNT_EMAIL,
        ];

        return isset($map[$type]) && $this->scopeConfig->isSetFlag($map[$type]);
    }

    public function getLocale(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            Data::XML_PATH_DEFAULT_LOCALE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    private function getValue(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
