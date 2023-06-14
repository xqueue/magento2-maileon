<?php
 
namespace Xqueue\Maileon\Api;
 
interface TestMarkAbandonedCartsInterface
{
    /**
     * Test mark abandoned carts method
     *
     * @param string $token
     *
     * @return string
     */
 
    public function testMarkAbandonedCarts($token);
}
