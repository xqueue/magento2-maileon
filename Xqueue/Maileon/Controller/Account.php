<?php

namespace Xqueue\Maileon\Controller;

use Magento\Store\Model\ScopeInterface;
use Xqueue\Maileon\Model\Maileon\ContactCreate;
use de\xqueue\maileon\api\client\utils\PingService;

class Account
{
    protected $request;

    protected $customerSession;

    protected $storeManager;

    /**
     * Plugin constructor.
     *
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
    }

    public function afterExecute(\Magento\Customer\Controller\Account\CreatePost $subject, $result)
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

        $customer = $this->customerSession->getCustomer();
        $id = $customer->getId();
        $email = $customer->getEmail();

        if ($module_enabled == 'yes') {
            if ((boolean)$this->request->getParam('is_subscribed', false)) {
                if (!empty($apikey) || !empty($permission)) {
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

                            $firstname = $customer->getFirstname();
                            $lastname = $customer->getLastname();
                            $fullname = $customer->getName();

                            $customer_data = array(
                                'firstname' => $firstname,
                                'lastname' => $lastname,
                                'fullname' => $fullname
                            );

                            $standard_fields = array();

                            $custom_fields = array(
                                'magento_storeview_id' => $storeId,
                                'magento_source' => 'newsletter'
                            );

                            $response = $sync->makeMalieonContact($customer_data, $standard_fields, $custom_fields);

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
                $logger->debug('The customer is already subscribed!');
            }
        } else {
            $logger->info('Newsletter module inactive!');
        }

        return $result;
    }
}
