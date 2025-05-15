# Magento 2 Maileon Module

This Magento 2 extension integrates [Maileon](https://maileon.com) — the professional email marketing platform — with your Magento store. It enables newsletter contact synchronization, double opt-in (DOI) handling, abandoned cart tracking, and more.

---

## 🛠️ Installation

Choose the appropriate method depending on the version you are installing.

---

### 📦 Install from ZIP using Composer (version ≥ 1.8.1)

1. Copy the module into your Magento root directory, for example:

   ```
   sources/magento-2/Xqueue/Maileon/
   ```

2. Modify your `composer.json` to include:

   ```json
   "require": {
       "xqueue/module-maileon": "^1.8"
   },
   "repositories": [
       {
           "type": "composer",
           "url": "https://repo.magento.com/"
       },
       {
           "name": "xqueue/module-maileon",
           "type": "path",
           "url": "sources/magento-2/Xqueue/Maileon"
       }
   ]
   ```

3. Run the following command:

   ```bash
   composer update
   ```

---

### 📦 Install via Composer (version ≥ 1.9.5)

If the module is available through a Composer repository:

```bash
composer require xqueue/module-maileon
```

---

## ✅ Enable the module

1. Check if Magento recognizes the module:

   ```bash
   php bin/magento module:status
   ```

   You should see:

   ```
   List of disabled modules:
   Xqueue_Maileon
   ```

2. Enable the module:

   ```bash
   php bin/magento module:enable Xqueue_Maileon
   ```

3. Register and install the module:

   ```bash
   php bin/magento setup:upgrade
   ```

4. (For production mode only) Compile dependencies and flush cache:

   ```bash
   php bin/magento setup:di:compile
   php bin/magento cache:flush
   ```

---

## ⚙️ Configuration

Once installed, go to:

```
Stores → Configuration → Maileon
```

...to configure your API key, permissions, and synchronization options.

---

## 📚 Documentation

Full user documentation is available at:

👉 [Maileon Magento 2 Documentation](https://xqueue.atlassian.net/wiki/spaces/MSI/pages/224199860/Magento+2)

---

© XQueue GmbH – All rights reserved.
