<?php

namespace Maileon\SyncPlugin\Cron;

use Magento\Store\Model\ScopeInterface;

class MarkAbandonedCarts
{
    protected $_logger;

    protected $_logFactory;

    protected $_messageManager;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Maileon\SyncPlugin\Model\LogFactory $logFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->_logger = $logger;
        $this->_logFactory = $logFactory;
        $this->_messageManager = $messageManager;
    }

    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $module_enabled = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/active_modul', ScopeInterface::SCOPE_STORE);

        if (empty($module_enabled) || $module_enabled == 'no') {
            return false;
        }

        try {
            $reminderPeriodInHours = $objectManager
                ->get('Magento\Framework\App\Config\ScopeConfigInterface')
                ->getValue('syncplugin/abandoned_cart/hours', ScopeInterface::SCOPE_STORE);

            if (!$reminderPeriodInHours || trim($reminderPeriodInHours) == "") {
                $reminderPeriodInHours = 48;
            }

            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();

            $select = $connection->select()
                ->from(
                    array('q' => $resource->getTableName('quote')),
                    array('store_id' => 'q.store_id',
                        'quote_id' => 'q.entity_id',
                        'customer_id' => 'q.customer_id',
                        'updated_at' => 'q.updated_at'
                    )
                )
                ->joinLeft(
                    array('a' =>$resource->getTableName('quote_address')),
                    'q.entity_id=a.quote_id AND a.address_type="billing"',
                    array('customer_email' => new \Zend_Db_Expr('IFNULL(q.customer_email, a.email)'),
                        'customer_firstname' => new \Zend_Db_Expr('IFNULL(q.customer_firstname, a.firstname)'),
                        'customer_middlename' => new \Zend_Db_Expr('IFNULL(q.customer_middlename, a.middlename)'),
                        'customer_lastname' => new \Zend_Db_Expr('IFNULL(q.customer_lastname, a.lastname)')
                    )
                )
                ->joinInner(
                    array('i' => $resource->getTableName('quote_item')),
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
                ->where('q.items_count>0')
                ->where('q.customer_email IS NOT NULL OR a.email IS NOT NULL')
                ->where('i.parent_item_id IS NULL')
                ->group(array('q.entity_id', 'a.email', 'a.firstname', 'a.middlename', 'a.lastname'))
                ->order('updated_at');

            $carts = $connection->fetchAll($select);

            foreach ($carts as $cart) {
                try {
                    // FIRST check if there is already a queued transaction that has not already been sent
                    $queueModel = $objectManager->create('Maileon\SyncPlugin\Model\Queue')->load(
                        $cart['customer_id'],
                        'customer_id'
                    );
                    if (count($queueModel->getData())) {
                        // If there is already an entry for the given customer ID, just continue,
                        // this can be the case if the
                        // abandoned cart checker runs more often than the job for sending transactions
                        continue;
                    } else {
                        $queueModel = $objectManager->get('Maileon\SyncPlugin\Model\Queue');
                    }

                    // New logic for finding most recent reminder and compare if this one is older than the last check
                    $logModel = $this->_logFactory->create();
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

                    $this->_logger->info('Queuing shopping cart for customer' . $cart['customer_email']);

                    $recipientName = $cart['customer_firstname'] . $cart['customer_lastname'];

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
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->_messageManager->addExceptionMessage(
                        $e,
                        __('There was a problem with searching abandoned carts: %1', $e->getMessage())
                    );
                }
            }

            return true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_messageManager->addExceptionMessage(
                $e,
                __('There was a problem with searching abandoned carts: %1', $e->getMessage())
            );
        }
    }
}
