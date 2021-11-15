<?php
 
namespace Xqueue\Maileon\Api;
 
interface MaileonTestAbandonedCartsInterface
{
    /**
     * Test mark abandoned carts method
     *
     * @param string $token
     *
     * @return string
     */
 
    public function testMarkAbandonedCarts($token);

    /**
     * Test send abandoned carts emails method
     *
     * @param string $token
     *
     * @return string
     */
 
    public function testSendAbandonedCartsEmails($token);
}
