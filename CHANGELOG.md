# 1.9.6
- Added `store_id` and `store_name` as generic fields to order confirmation and abandoned cart transactions

# 1.9.5
- Fix product.short_description max. 1000 char length at  orders extended transaction types

# 1.9.4
- Fix order.categories and order.product_ids max. 1000 char length at transaction types
- Add plugin status reporter cron job

# 1.9.3
- Add getCustomOrderTransactionAttributes() helper method to order confirm transaction

# 1.9.2
- Fix Maileon confirm webhook when the subscriber not exists at Magento 2 

# 1.9.1
- Fix mixed type declarations

# 1.9.0

- Add customer account related transactions
- Add order related transactions
- Improve abandoned carts functionality

# 1.8.12

- Bugfix AfterPlaceOrder product categories

# 1.8.11

- Abandoned cart work with storeviews

# 1.8.10

- Update Maileon Php API client minimum version

# 1.8.9

- Add payment method id and name to the order confirmation transactions

# 1.8.8

- Fix abandoned cart images urls

# 1.8.7

- Abandoned cart thumbnail url change
- Fix to work with Magento 2.4.4

# 1.8.6

- DB table create change to declarative schema

# 1.8.5

- Add thumbnail image to abandoned carts transaction

# 1.8.4

- Maileon webhooks check the storeview id exists or not
- Protect abandoned carts test webhook with token and plugin config setting
- Bugfix, blank page after NL subscribe

# 1.8.3

- Add functionality to add separate Maileon permission for buyers

# 1.8.2

- Resubscribe with same email address fix

# 1.8.1

- Version logic change
- Recreate the package to work with composer

# 1.0.8

- Plugin configuration extend to Store view level
- Add extra parameter to DOI confirmation and unsubscribe webhook
- Add a custom field to contact: magento_storeview_id
- Add a custom field to contact: magento_source
- It can be newsletter, order_confirmation and abandoned_cart

# 1.0.7

- Change permission logic
- Add DOI+ process

# 1.0.6

- Change DOI process logic
- Add fallback permission to transactions

# 1.0.5

- Add new Maileon webhooks api urls, to process doi confirm and unsubscribe webhooks
- Bug fix at abandoned carts methods
- Add new fields to order transactions and round the float values to two digits

# 1.0.4

- Add order, order extended and abandoned carts transaction types 2.0 version with a lot new attribute fields
- Add Helper class to add extra fields to order or abandoned carts transactions with an external plugin

# 1.0.3

- Add order extended transaction send

# 1.0.2

- Bugfix at the Maileon webhooks

# 1.0.1

- Add abandoned cart functionality

# 1.0.0

- First release based on the Magento 1 plugin
