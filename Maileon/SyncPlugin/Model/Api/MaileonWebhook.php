<?php
 
namespace Maileon\SyncPlugin\Model\Api;
 
use Psr\Log\LoggerInterface;
use Magento\Store\Model\ScopeInterface;
 
class MaileonWebhook
{
    protected $logger;
    protected $subscriber;
    protected $storeManager;
 
    public function __construct(
        LoggerInterface $logger,
        \Magento\Newsletter\Model\Subscriber $subscriber,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->subscriber = $subscriber;
        $this->storeManager = $storeManager;
    }
 
    /**
     * @inheritdoc
     */
 
    public function getUnsubscribeWebhook($email, $token, $storeview_id = null)
    {
        $response = ['success' => false];
        $email = preg_replace('/\s+/', '+', $email);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $unsubscribe_token = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/unsubscribe_token', ScopeInterface::SCOPE_STORE);

        $unsubscribe_all_emails = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/unsubscribe_all_emails', ScopeInterface::SCOPE_STORE);
 
        try {
            if (!empty($email) && !empty($token)) {
                if ($token == $unsubscribe_token) {
                    if (!empty($storeview_id)) {
                        $this->storeManager->setCurrentStore((string) $storeview_id);

                        $subscriber = $this->subscriber->loadByEmail($email);

                        if ($subscriber->isSubscribed()) {
                            $subscriber->unsubscribe();
                            $response = [
                                'success' => true,
                                'message' => 'Unsubscribed: ' . $email . ', Store Id: ' . $storeview_id
                            ];
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Not exsisting subscriber! Email: ' . $email . ', Store Id: ' . $storeview_id
                            ];
                        }
                    } else {
                        if (filter_var($unsubscribe_all_emails, FILTER_VALIDATE_BOOLEAN)) {
                            $stores = $this->storeManager->getStores();
                            $unsubscribed_from = array();

                            foreach ($stores as $store) {
                                $this->storeManager->setCurrentStore($store->getId());

                                $subscriber = $this->subscriber->loadByEmail($email);

                                if ($subscriber->isSubscribed()) {
                                    $subscriber->unsubscribe();
                                    $unsubscribed_from[] = $store->getId();
                                }
                            }

                            if (!empty($unsubscribed_from)) {
                                $response = [
                                    'success' => true,
                                    'message' => 'Unsubscribed: ' . $email . ', Store Id: ' . implode(',', $unsubscribed_from)
                                ];
                            } else {
                                $response = [
                                    'success' => false,
                                    'message' => 'Not exsisting subscriber! Email: ' . $email
                                ];
                            }
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Storeview id is empty and unsubscribe all email disabled! Email: ' . $email
                            ];
                        }
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Bad unsubscriber token!'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Empty email or token params!'];
            }
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            $this->logger->info($e->getMessage());
        }

        $returnArray = json_encode($response);
        return $returnArray;
    }

    /**
     * @inheritdoc
     */
 
    public function getDoiConfirmWebhook($email, $token, $storeview_id)
    {
        $response = ['success' => false];
        $email = preg_replace('/\s+/', '+', $email);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $doi_token = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/doi_token', ScopeInterface::SCOPE_STORE);
 
        try {
            if (!empty($email) && !empty($token)) {
                if ($token == $doi_token) {
                    if (!empty($storeview_id)) {
                        $this->storeManager->setCurrentStore((string) $storeview_id);
                    }

                    $subscriber = $this->subscriber->loadByEmail($email);

                    if (!empty($subscriber)) {
                        $confirm_code = $subscriber->getCode();
                        $subscriber->confirm($confirm_code);

                        $response = ['success' => true, 'message' => 'Confirmed: ' . $email];
                    } else {
                        $response = ['success' => false, 'message' => 'Not exsisting subscriber!'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Bad DOI token!'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Empty email or token params!'];
            }
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            $this->logger->info($e->getMessage());
        }

        $returnArray = json_encode($response);
        return $returnArray;
    }
}
