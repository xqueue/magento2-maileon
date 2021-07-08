<?php

namespace Xqueue\Maileon\Model\Newsletter;

use Magento\Store\Model\ScopeInterface;
use Magento\Newsletter\Model\Subscriber as MagentoSubscriber;

/**
 * Don't send any newsletter-related emails.
 * These will all go out through our marketing platform.
 */
class Subscriber
{
    /**
     * @param MagentoSubscriber $oSubject
     * @param callable $proceed
     */
    public function aroundSendConfirmationRequestEmail(MagentoSubscriber $oSubject, callable $proceed)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $disable_request_email = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/disable_request_email', ScopeInterface::SCOPE_STORE);

        if ($disable_request_email == 'yes') {
            $result = null;
        } else {
            $result = $proceed();
        }

        return $result;
    }

    /**
     * @param MagentoSubscriber $oSubject
     * @param callable $proceed
     */
    public function aroundSendConfirmationSuccessEmail(MagentoSubscriber $oSubject, callable $proceed)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $disable_success_email = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/disable_success_email', ScopeInterface::SCOPE_STORE);

        if ($disable_success_email == 'yes') {
            $result = null;
        } else {
            $result = $proceed();
        }

        return $result;
    }

    /**
     * @param MagentoSubscriber $oSubject
     * @param callable $proceed
     */
    public function aroundSendUnsubscriptionEmail(MagentoSubscriber $oSubject, callable $proceed)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $disable_unsubscription_email = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/disable_unsubscription_email', ScopeInterface::SCOPE_STORE);

        if ($disable_unsubscription_email == 'yes') {
            $result = null;
        } else {
            $result = $proceed();
        }

        return $result;
    }
}
