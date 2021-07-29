<?php

namespace Xqueue\Maileon\Controller;

use Magento\Store\Model\ScopeInterface;
use Xqueue\Maileon\Model\Maileon\ContactCreate;
use de\xqueue\maileon\api\client\utils\PingService;

class Subscriber
{
    protected $request;

    protected $storeManager;

    /**
     * Subscriber factory
     *
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * Plugin constructor.
     *
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory
    ) {
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->subscriberFactory = $subscriberFactory;
    }

    public function afterExecute(\Magento\Newsletter\Controller\Subscriber\NewAction $subject, $result)
    {
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $storeId = $this->storeManager->getStore()->getId();

        $apikey = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/api_key', ScopeInterface::SCOPE_STORE);

        $permission = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/permission', ScopeInterface::SCOPE_STORE);

        $doiprocess = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/doi_process', ScopeInterface::SCOPE_STORE);

        $doiplusprocess = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/doi_plus_process', ScopeInterface::SCOPE_STORE);

        $doikey = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/doi_key', ScopeInterface::SCOPE_STORE);

        $print_curl = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/print_curl', ScopeInterface::SCOPE_STORE);

        $module_enabled = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/active_modul', ScopeInterface::SCOPE_STORE);

        $email = (string) $this->request->getParam('email');

        if ($module_enabled == 'yes') {
            if (!empty($email)) {
                if (!$this->emailIsExists($email)) {
                    if (!empty($apikey) || !empty($permission)) {
                        $params = $this->request->getParams();
                        $custom_fields = array(
                            'magento_storeview_id' => $storeId,
                            'magento_source' => 'newsletter'
                        );
                        $standard_fields = array();

                        if (!empty($params)) {
                            foreach ($params as $key => $value) {
                                $exploded_key = explode('_', $key);
                                
                                if ($exploded_key[0] == 'standard') {
                                    $standard_fields[$exploded_key[1]] = $value;
                                }

                                if ($exploded_key[0] == 'custom') {
                                    $custom_fields[$exploded_key[1]] = $value;
                                }
                            }
                        }

                        $maileon_config = array(
                            'BASE_URI' => 'https://api.maileon.com/1.0',
                            'API_KEY' => $apikey,
                            'TIMEOUT' => 15
                        );

                        $ping_service = new PingService($maileon_config);
                        $response = $ping_service->pingGet();
                        $ping_result = $response->getStatusCode();

                        if ($ping_result == 401) {
                            $logger->debug('Maileon API key is wrong!');
                        } else {
                            try {
                                $sync = new ContactCreate(
                                    $apikey,
                                    $email,
                                    $permission,
                                    $doiprocess,
                                    $doiplusprocess,
                                    $doikey,
                                    $print_curl
                                );
                                
                                $response = $sync->makeMalieonContact(null, $standard_fields, $custom_fields);

                                if ($response) {
                                    $logger->info('Contact subscribe Done!');
                                } else {
                                    $logger->debug('Contact subscribe Failed!');
                                }
                            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                                $this->messageManager->addException(
                                    $e,
                                    __('There was a problem with the Maileon subscription: %1', $e->getMessage())
                                );
                            }
                        }
                    } else {
                        $logger->debug('Maileon API key or permission settings is empty!');
                    }
                } else {
                    $logger->debug('Subscriber already exists!');
                }
            } else {
                $logger->debug('Email field is empty!');
            }
        } else {
            $logger->info('Newsletter module inactive!');
        }
    }

    private function emailIsExists(string $email)
    {
        $subscriber = $this->subscriberFactory->create()->loadByEmail($email);

        if ($subscriber->getId()
            && (int) $subscriber->getSubscriberStatus() === \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED
        ) {
            return true;
        }

        return false;
    }
}
