<?php

namespace Xqueue\Maileon\Helper\External;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    /**
     * This method can return an associative array of special information for the given products.
     * @param array $product
     * @return array
     */
    public function getCustomProductAttributes(array $product): array
    {
        $result = array();
        
        if (!$product) {
            return $result;
        }
        
        /**
         * Customer code here.
         * Hint: the name of the attribute does not matter, you can freely choose and use the same name
         * for configuring your Maileon mailing.
         * $result['myOwnAttribute'] = "special information about the product";
         */
        
        return $result;
    }

    /**
     * This method can return an associative array of special information for the given order transaction.
     *
     * @param array $transactionContent
     * @return array
     */
    public function getCustomOrderTransactionAttributes(array $transactionContent): array
    {
        $result = array();

        if (!$transactionContent) {
            return $result;
        }

        /**
         * Customer code here.
         * Hint: As the outer skeleton of the transaction is defined (the transaction type),
         * you need to choose a valid (existing) property name.
         * See transaction type "magento_orders_v2" for valid fields.
         * $result['generic.string_1'] = "this is some additional information";
         * $result['generic.string_2'] = "this is another additional information";
         * e.g. collected from an external database";
         */

        return $result;
    }

    /**
     * This method can return an associative array of special information for the given order transaction.
     *
     * @param array $transactionExtContent
     * @return array
     */
    public function getCustomOrderExtendedTransactionAttributes(array $transactionExtContent): array
    {
        $result = array();

        if (!$transactionExtContent) {
            return $result;
        }

        /**
         * Customer code here.
         * Hint: As the outer skeleton of the transaction is defined (the transaction type),
         * you need to choose a valid (existing) property name.
         * See transaction type "magento_orders_extended_v2" for valid fields.
         * $result['generic.string_1'] = "this is some additional information";
         * $result['generic.string_2'] = "this is another additional information";
         * e.g. collected from an external database";
         */

        return $result;
    }

    /**
     * This method can return an associative array of special information for the given abandoned cart products.
     *
     * @param array $product
     * @return array
     */
    public function getCustomAbandonedCartProductAttributes(array $product): array
    {
        $result = array();
        
        if (!$product) {
            return $result;
        }
        
        /**
         * Customer code here.
         * Hint: the name of the attribute does not matter, you can freely choose and use the same name
         * for configuring your Maileon mailing.
         * $result['myOwnAttribute'] = "special information about the product";
         */
        
        return $result;
    }

    /**
     * This method can return an associative array of special information for the given abandoned cart transaction.
     *
     * @param array $transactionContent
     * @return array
     */
    public function getCustomAbandonedCartTransactionAttributes(array $transactionContent): array
    {
        $result = array();

        if (!$transactionContent) {
            return $result;
        }

        /**
         * Customer code here.
         * Hint: As the outer skeleton of the transaction is defined (the transaction type),
         * you need to choose a valid (existing) property name.
         * See transaction type "magento_abandoned_carts_v2" for valid fields.
         * $result['generic.string_1'] = "this is some additional information";
         * $result['generic.string_2'] = "this is another additional information";
         * e.g. collected from an external database";
         */

        return $result;
    }
}
