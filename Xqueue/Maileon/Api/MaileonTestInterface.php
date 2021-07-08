<?php
 
namespace Xqueue\Maileon\Api;
 
interface MaileonTestInterface
{
    /**
     * Test
     *
     * @return string
     */
 
    public function testMarkAbandonedCarts();

    /**
     * Test
     *
     * @return string
     */
 
    public function testSendAbandonedCartsEmails();
}
