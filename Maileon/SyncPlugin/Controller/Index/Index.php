<?php

namespace Maileon\SyncPlugin\Controller\Index;

use Magento\Store\Model\ScopeInterface;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $_request;

    protected $_subscriber;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Newsletter\Model\Subscriber $subscriber
    ) {
        $this->_request = $request;
        $this->_subscriber = $subscriber;
        return parent::__construct($context);
    }

    public function execute()
    {
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $unsubscribe_token = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/unsubscribe_token', ScopeInterface::SCOPE_STORE);

        $doi_token = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/doi_token', ScopeInterface::SCOPE_STORE);

        $token = $this->_request->getParam('token');
        $email = $this->_request->getParam('email');
        $doi = $this->_request->getParam('doi');

        $logger->info($token);
        $logger->info($email);

        if (!empty($email) && !empty($token)) {
            if ($doi == '1') {
                if ($token == $doi_token) {
                    $subscriber = $this->_subscriber->loadByEmail($email);

                    if (!empty($subscriber)) {
                        $confirm_code = $subscriber->getCode();
                        $subscriber->confirm($confirm_code);

                        $logger->info('Confirmed: ' . $email);
                    } else {
                        $logger->debug('Not exsisting subscriber!');
                    }
                } else {
                    $logger->debug('Bad DOI token!');
                }
            } else {
                if ($token == $unsubscribe_token) {
                    $subscriber = $this->_subscriber->loadByEmail($email);

                    if ($subscriber->isSubscribed()) {
                        $subscriber->unsubscribe();
                        $logger->info('Unsubscribed: ' . $email);
                    } else {
                        $logger->debug('Not exsisting subscriber!');
                    }
                } else {
                    $logger->debug('Bad unsubscriber token!');
                }
            }
        } else {
            $logger->debug('Empty email or code params!');
        }
    }
}
