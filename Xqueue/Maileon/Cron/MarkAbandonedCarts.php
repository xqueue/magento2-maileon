<?php

namespace Xqueue\Maileon\Cron;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class MarkAbandonedCarts
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var \Xqueue\Maileon\Model\LogFactory
     */
    protected $logFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Xqueue\Maileon\Model\LogFactory $logFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->logFactory = $logFactory;
        $this->storeManager = $storeManager;
        $this->messageManager = $messageManager;
    }

    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $config = $this->getPluginConfigValues();

        $storeIds = array_keys($this->storeManager->getStores(false));

        $enabledStoreIds = $this->checkStoreConfigModuleEnabled($storeIds);

        $validateResult = $this->validateConfigValues($config);

        if (empty($enabledStoreIds)) {
            return false;
        }

        if (!$validateResult['success']) {
            return false;
        }

        try {
            $reminderPeriodInHours = (int) $config['reminderPeriodInHours'];

            $carts = $this->getCartsFromQuote($reminderPeriodInHours, $enabledStoreIds);

            foreach ($carts as $cart) {
                try {
                    // FIRST check if there is already a queued transaction that has not already been sent
                    $queueModel = $objectManager->create('Xqueue\Maileon\Model\Queue')->load(
                        $cart['customer_id'],
                        'customer_id'
                    );

                    if (count($queueModel->getData())) {
                        // If there is already an entry for the given customer ID, just continue,
                        // this can be the case if the
                        // abandoned cart checker runs more often than the job for sending transactions
                        continue;
                    } else {
                        $queueModel = $objectManager->create('Xqueue\Maileon\Model\Queue');
                    }

                    // New logic for finding most recent reminder and compare if this one is older than the last check
                    $logModel = $this->logFactory->create();
                    $abandonedcartsCollection = $logModel->getCollection();
                    $abandonedcartsModel = $abandonedcartsCollection
                        ->addFieldToFilter('customer_id', $cart['customer_id'])
                        ->setOrder('sent_at', 'DESC')
                        ->load()
                        ->getFirstItem();

                    if (!empty($abandonedcartsModel->getData())) {
                        if ($abandonedcartsModel->getSentAt() >= $cart['updated_at']) {
                            continue;
                        }
                    }

                    $this->logger->info('Queuing shopping cart for customer' . $cart['customer_email']);

                    $this->saveQueue($queueModel, $cart);
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->messageManager->addExceptionMessage(
                        $e,
                        __('There was a problem with searching abandoned carts: %1', $e->getMessage())
                    );
                }
            }

            return true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with searching abandoned carts: %1', $e->getMessage())
            );
        }
    }

    /**
     * Get the config values
     *
     * @param integer|null $storeId
     * @return array
     */
    private function getPluginConfigValues(?int $storeId = null)
    {
        $config['moduleEnabled'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/active_modul',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $config['reminderPeriodInHours'] = (int) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/hours',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        return $config;
    }

    /**
     * Checking all storeviews to see if any are the abandoned carts module is enabled.
     *
     * @param array $storeIds
     * @return array
     */
    private function checkStoreConfigModuleEnabled(array $storeIds)
    {
        $enabledStoreIds = [];

        foreach ($storeIds as $storeId) {
            $config = $this->getPluginConfigValues($storeId);
            if ($config['moduleEnabled'] === 'yes') {
                $enabledStoreIds[] = (int) $storeId;
            }
        }

        return $enabledStoreIds;
    }

    /**
     * Validate the config values.
     *
     * @param array $config
     * @return array
     */
    private function validateConfigValues(array $config)
    {
        if (empty($config['reminderPeriodInHours'])) {
            return [
                'success' => false,
                'message' => 'Reminder period in hours is empty!'
            ];
        }

        return [
            'success' => true,
            'message' => 'Every config value is OK!'
        ];
    }

    /**
     * Get the carts from database
     *
     * @param integer $reminderPeriodInHours
     * @param array $storeIds
     * @return array
     */
    protected function getCartsFromQuote(int $reminderPeriodInHours, array $storeIds)
    {
        try {
            $connection = $this->resourceConnection->getConnection();

            $select = $connection->select()
                ->from(
                    array('q' => $this->resourceConnection->getTableName('quote')),
                    array('store_id' => 'q.store_id',
                        'quote_id' => 'q.entity_id',
                        'customer_id' => 'q.customer_id',
                        'updated_at' => 'q.updated_at'
                    )
                )
                ->joinLeft(
                    array('a' =>$this->resourceConnection->getTableName('quote_address')),
                    'q.entity_id=a.quote_id AND a.address_type="billing"',
                    array('customer_email' => new \Zend_Db_Expr('IFNULL(q.customer_email, a.email)'),
                        'customer_firstname' => new \Zend_Db_Expr('IFNULL(q.customer_firstname, a.firstname)'),
                        'customer_middlename' => new \Zend_Db_Expr('IFNULL(q.customer_middlename, a.middlename)'),
                        'customer_lastname' => new \Zend_Db_Expr('IFNULL(q.customer_lastname, a.lastname)')
                    )
                )
                ->joinInner(
                    array('i' => $this->resourceConnection->getTableName('quote_item')),
                    'q.entity_id=i.quote_id',
                    array(
                        'product_ids' => new \Zend_Db_Expr('GROUP_CONCAT(i.product_id)'),
                        'item_ids' => new \Zend_Db_Expr('GROUP_CONCAT(i.item_id)')
                    )
                )
                ->where('q.is_active=1')
                // go back as far as requested and then two hours
                ->where('q.updated_at > ?', date(
                    'Y-m-d H:i:s',
                    time() - intval($reminderPeriodInHours) * 60 * 60 - 2 * 60 * 60
                ))
                ->where('q.updated_at < ?', date('Y-m-d H:i:s', time() - intval($reminderPeriodInHours) * 60 * 60))
                ->where('q.store_id IN (?)', $storeIds)
                ->where('q.items_count>0')
                ->where('q.customer_email IS NOT NULL OR a.email IS NOT NULL')
                ->where('i.parent_item_id IS NULL')
                ->group(array('q.entity_id', 'a.email', 'a.firstname', 'a.middlename', 'a.lastname'))
                ->order('updated_at');

            return $connection->fetchAll($select);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with searching abandoned carts: %1', $e->getMessage())
            );
        }
    }

    /**
     * Save the Queue to database
     *
     * @param \Xqueue\Maileon\Model\Queue $queueModel
     * @param array $cart
     * @return void
     */
    protected function saveQueue(\Xqueue\Maileon\Model\Queue $queueModel, array $cart)
    {
        $recipientName = $cart['customer_firstname'] . ' ' . $cart['customer_lastname'];

        $queueModel->setUpdatedAt($cart['updated_at']);
        $queueModel->setCreatedAt(date('Y-m-d H:i:s'));
        $queueModel->setRecipientName($recipientName);
        $queueModel->setRecipientEmail($cart['customer_email']);
        $queueModel->setStoreId($cart['store_id']);
        $queueModel->setProductIds($cart['product_ids']);
        $queueModel->setCategoryIds($cart['item_ids']);
        $queueModel->setCustomerId($cart['customer_id']);
        $queueModel->setQuoteId($cart['quote_id']);
        $queueModel->save();
    }
}
