**Magento 2 Maileon module**


## Install the module

First step is copy the Maileon plugin to `app/code`. If the `code` directory don't exsist, create a new directory to `app` named `code`.

### Enable the module

Before enable the module, we must check to make sure Magento has recognize our module or not by enter the following at the command line:

~~~
php bin/magento module:status
~~~

If you follow above step, you will see this in the result:

~~~
List of disabled modules:
Xqueue_Maileon
~~~

This means the module has recognized by the system but it is still disabled. Run this command to enable it:

~~~
php bin/magento module:enable Xqueue_Maileon
~~~

The module has enabled successfully if you saw this result:

~~~
The following modules has been enabled:
- Xqueue_Maileon
~~~

This’s the first time you enable this module so Magento require to check and upgrade module database. We need to run this comment:

~~~
php bin/magento setup:upgrade
~~~

Now you can check under `Stores -> Configuration -> Maileon` that the module is present.
