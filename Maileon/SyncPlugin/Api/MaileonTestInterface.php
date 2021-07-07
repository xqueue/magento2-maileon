<?php
 
namespace Maileon\SyncPlugin\Api;
 
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
