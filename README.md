# My Tickets Manager 🎟️

**My Tickets Manager** is a lightweight, modern, and highly configurable open-source ticket management system built from scratch using PHP 8, MySQL/MariaDB, and Bootstrap 5. Designed for simplicity, speed, and privacy, it allows both registered users and unauthenticated guests to submit and track support requests effortlessly.

---

## ✨ Features

* **Guest & Registered Ticket Creation**: Guests can submit tickets with just their name and email, receiving a unique tracking code and a secure access token via email.
* **Rich Text Editing**: Integrated with **My-WYSIWYG** for rich-text formatting directly on submit and reply textareas.
* **Dynamic & Responsive UI**: Collapsible sidebar with mobile-first responsive layout and multi-language support.
* **Event Hook Plugin Engine**: Modular architecture allowing custom extensions (e.g., Discord webhook notifications) without modifying core source files.
* **Automated Translations (i18n)**: JSON-based internationalization featuring an automated translator tool powered by **LibreTranslate**.
* **MYETV SSO & OAuth Integration**: Support for login and registration via MYETV, Google, and Facebook accounts.
* **Cloudflare Turnstile Captcha**: Built-in protection against spam and automated bots on forms.
* **Custom SMTP Mailing**: Support for PHPMailer or native PHP `mail()` for notification dispatches.

---

## 🛠️ System Requirements

* **PHP**: `^8.0` or higher (with `pdo`, `pdo_mysql`, `curl`, and `json` extensions enabled).
* **Database**: MySQL `^5.7` or MariaDB `^10.3`.
* **Web Server**: Apache (`mod_rewrite` recommended) or Nginx.
* **Permissions**: Write permissions on `/translations/` directory.

---

## 🚀 Installation Guide

### Step 1: Clone the Repository
```bash
git clone [https://github.com/OskarCosimo/my-tickets-manager.git](https://github.com/YOUR_USERNAME/my-tickets-manager.git)
cd my-tickets-manager

```

### Step 2: Set Up Directory Permissions

If you want to use libretranslate for translations, make sure the `translations/` directory is writable by your web server user (`www-data` or `apache`):

```bash
chmod -R 775 translations/
chown -R www-data:www-data translations/

```

### Step 3: Database Import

Import the database schema using MySQL CLI or DBeaver:

```sql
The database.sql file contains all the sql scripts to build your database
```

### Step 4: Configure Database Connection

Edit `includes/config.php` and set your MySQL database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

```

---

## 🌍 Internationalization & Translations (i18n)

The application uses JSON files stored inside `/translations/` for UI strings:

* **Base Language File**: `translations/lang-en.json`

### Creating Manual Translations

You can manually create a new file named `translations/lang-[code].json` (e.g., `lang-it.json`) and translate the string key-value pairs.

### Automated Translations via LibreTranslate

1. Go to **Admin Panel -> Settings** and set your **LibreTranslate Endpoint URL** (e.g., `http://your-libretranslate-server:5055/translate`).
2. Go to **Admin Panel -> Translations**.
3. Enter the target language code (e.g., `it`, `es`, `fr`) and click **Generate Translation JSON**. The system will automatically read `lang-en.json`, translate all strings using LibreTranslate, and write `lang-[code].json` to disk.

---

## 🔌 Plugin System (Hooks Architecture)

**My Tickets Manager** features a zero-core-modification event engine.

### How to Create a Plugin

1. Create a subdirectory inside `/plugins/` (e.g., `/plugins/my_custom_plugin/`).
2. Create a PHP file inside it (e.g., `/plugins/my_custom_plugin/plugin.php`).
3. Use the `add_hook()` function to listen to platform events:

```php
<?php
// Plugin Name: My Custom Plugin

add_hook('on_ticket_created', function($data) {
    $ticket = $data['ticket'];
    // Your custom code here (e.g., API call, SMS alert, etc.)
});

add_hook('on_ticket_replied', function($data) {
    $ticket = $data['ticket'];
    $reply = $data['reply'];
    // Your custom code here
});

```

### Included Plugins

* **Discord Notifications (`plugins/discord_notifier/discord_plugin.php`)**: Sends instant rich embeds to a Discord channel via Webhook when a new ticket is submitted or replied to. Set the `discord_webhook_url` in settings to enable it.

---

## ⚙️ Pre-filled Form Links

You can share external URLs or submit POST forms with pre-populated values to speed up ticket creation:

```text
[https://[YOUR_WEBSITE]/submit.php?name=Mario+Rossi&email=mario@myetv.tv&category=2&subject=Login+Issue&message=I+cannot+login](https://[YOUR_WEBSITE]/submit.php?name=Mario+Rossi&email=mario@myetv.tv&category=2&subject=Login+Issue&message=I+cannot+login)

```

* Supported parameters: `name`, `email`, `subject`, `category` (or `category_id`), `message` (or `msg`).

---

## 📄 License

This project is open-source software licensed under the [MIT License](https://www.google.com/search?q=LICENSE).

```
