<?php
 
namespace Xqueue\Maileon\Api;

interface MaileonWebhookInterface
{
    /**
     * GET unsubscribe webhook from Maileon
     * @param string $email
     * @param string $token
     * @param string|null $storeview_id
     * @return string
     */
    public function getUnsubscribeWebhook(string $email, string $token, ?string $storeview_id = null): string;

    /**
     * GET doi confirm webhook from Maileon
     * @param string $email
     * @param string $token
     * @param string $storeview_id
     * @return string
     */
    public function getDoiConfirmWebhook(string $email, string $token, string $storeview_id): string;
}
