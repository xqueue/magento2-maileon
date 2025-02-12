<?php
 
namespace Xqueue\Maileon\Api;
 
interface MaileonWebhookInterface
{
    /**
     * GET unsubscribe webhook from Maileon
     * @param string $email
     * @param string $token
     * @param string $storeview_id
     * @return string
     */
 
    public function getUnsubscribeWebhook($email, $token, $storeview_id = null);

    /**
     * GET doi confirm webhook from Maileon
     * @param string $email
     * @param string $token
     * @param string $storeview_id
     * @return string
     */
 
    public function getDoiConfirmWebhook($email, $token, $storeview_id);
}
