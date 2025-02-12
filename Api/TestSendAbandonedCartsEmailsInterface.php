<?php
 
namespace Xqueue\Maileon\Api;
 
interface TestSendAbandonedCartsEmailsInterface
{
    /**
     * Test send abandoned carts emails method
     *
     * @param string $token
     *
     * @return string
     */
 
    public function testSendAbandonedCartsEmails($token);
}
