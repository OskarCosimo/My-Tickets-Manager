# My Tickets Manager 🎟️

**My Tickets Manager** is a lightweight, modern, and highly configurable open-source ticket management system built from scratch using PHP 8, MySQL/MariaDB, and Bootstrap 5. Designed for simplicity, speed, and privacy, it allows both registered users and unauthenticated guests to submit and track support requests effortlessly.

---

## ✨ Key Features

* **Guest & Registered Ticket Creation**: Guests can submit tickets with just their name and email, receiving a unique tracking code and a secure access token via email.
* **Web Installation Wizard**: Easy setup via `install.php` with automatic environment checks, database creation, and admin account setup.
* **1-Click Automatic Updates**: In-app updater that checks GitHub Releases for new code, applies incremental database migrations (`migrate.php`), and preserves existing configuration files.
* **Two-Factor Authentication (2FA)**: TOTP-based 2FA support for local accounts and SSO logins.
* **AI Assistance & Queue System**: Asynchronous background queue integration for AI-powered ticket summary and response assistance (supporting local Ollama, API Gemini).
* **SSO & OAuth Integration**: Single Sign-On integration for MYETV, Google, Microsoft, and Facebook accounts.
* **Rich Text Editing**: Integrated with **My-WYSIWYG** for rich-text formatting directly on submit and reply textareas.
* **Automated Translations (i18n)**: JSON-based internationalization featuring an automated translator tool powered by **LibreTranslate** (optional).
* **Event Hook Plugin Engine**: Modular architecture allowing custom extensions (e.g., Discord webhook notifications) without modifying core source files.
* **Cloudflare Turnstile Captcha**: Built-in protection against spam and automated bots on forms.
* **Custom SMTP Mailing**: Support for PHPMailer or native PHP `mail()` for notification dispatches.

---

## 🛠️ System Requirements

* **PHP**: `^8.0` or higher with the following extensions enabled:
  * `pdo_mysql`
  * `curl`
  * `zip` (required for automatic updates)
  * `json`
* **Database**: MySQL `^8.0` or MariaDB `^10.3`.
* **Web Server**: Apache (`mod_rewrite` recommended) or Nginx.
* **File Permissions**: Write access for the web server user (`www-data` or `apache`) on the root directory for automated updates and configuration generation.

---

## 🚀 Installation Guide

### Step 1: Clone the Repository
```bash
git clone [https://github.com/OskarCosimo/My-Tickets-Manager.git](https://github.com/OskarCosimo/My-Tickets-Manager.git)
cd My-Tickets-Manager

```

### Step 2: Set Directory Permissions

Assign ownership to the web server user so the web installer can write `includes/config.php` and the updater can manage release extractions:

```bash
sudo chown -R www-data:www-data /var/www/html/My-Tickets-Manager
sudo chmod -R 755 /var/www/html/My-Tickets-Manager

```

### Step 3: Run the Web Installer

Open your browser and navigate to the installation wizard:

```text
[http://your-domain.com/install.php](http://your-domain.com/install.php)

```

The web installer will automatically:

1. Verify system requirements and write permissions.
2. Prompt for database credentials and site settings.
3. Import the database schema (`database.sql`).
4. Create the initial Administrator account.
5. Generate the `includes/config.php` configuration file.

---

## 🔄 Automatic System Updates

**My Tickets Manager** includes a built-in update mechanism powered by GitHub Releases.

1. Navigate to **Admin Panel -> System Updates** (`/admin/update.php`).
2. The system queries GitHub Releases to check if a newer version is available.
3. Clicking **Update System Now**:
* Verifies file write permissions across the codebase.
* Downloads the latest release archive from GitHub.
* Extracts new files while protecting sensitive local files (`includes/config.php`, `.htaccess`, custom assets).
* Runs incremental database schema migrations automatically (`migrate.php`).



---

## 🤖 AI Background Queue Integration

The system features an asynchronous AI queue system (`ai_queue` table) for automated ticket processing (e.g., auto-summarization or agent reply suggestions).

* Configure your AI provider settings (Endpoint, API Key, Model) in **Admin Panel -> Settings**.
* The queue system is already up and running builded with MYSQL without making a dedicated cronjob in the server
* As optional feature you can also process pending AI jobs via cron job or CLI worker:

```bash
php api/process_ai_queue.php

```

---

## 🔒 Two-Factor Authentication (2FA)

Users and administrators can enable TOTP 2FA (Google Authenticator, Authy, 1Password) from their **Account Settings** profile.

* Works across both regular email/password logins and OAuth/SSO flows.
* Requires entering a valid 6-digit verification code (`/login_2fa.php`) before accessing the account.

---

## 🌍 Internationalization & Translations (i18n)

The application uses JSON files stored inside `/translations/` for UI strings:

* **Base Language File**: `translations/lang-en.json`

### Creating Manual Translations

Create a file named `translations/lang-[code].json` (e.g., `lang-it.json`) and translate the key-value pairs.

### Automated Translations via LibreTranslate

1. Go to **Admin Panel -> Settings** and set your **LibreTranslate Endpoint URL** (e.g., `http://your-libretranslate-server:5055/translate`).
2. Go to **Admin Panel -> Translations**.
3. Enter the target language code (e.g., `it`, `es`, `fr`) and click **Generate Translation JSON**. The system will read `lang-en.json`, translate all strings via LibreTranslate, and output `lang-[code].json`.

---

## 🔌 Plugin System (Hooks Architecture)

**My Tickets Manager** features a zero-core-modification event engine.

### How to Create a Plugin

1. Create a subdirectory inside `/plugins/` (e.g., `/plugins/my_custom_plugin/`).
2. Create a PHP file inside it (e.g., `/plugins/my_custom_plugin/plugin.php`).
3. Attach listener callbacks using `add_hook()`:

```php
<?php
// Plugin Name: My Custom Plugin

add_hook('on_ticket_created', function($data) {
    $ticket = $data['ticket'];
    // Custom logic (API dispatch, SMS alert, etc.)
});

add_hook('on_ticket_replied', function($data) {
    $ticket = $data['ticket'];
    $reply = $data['reply'];
    // Custom logic
});

```

### Included Plugins

* **Discord Notifications (`plugins/discord_notifier/discord_plugin.php`)**: Sends instant rich embeds to a Discord channel via Webhook when a ticket is created or replied to. Configure the `discord_webhook_url` setting to enable it.

---

## ⚙️ Pre-filled Form Links

Speed up ticket creation by sharing pre-populated form URLs:

```text
[https://your-domain.com/submit.php?name=Mario+Rossi&email=mario@domain.com&category=2&subject=Login+Issue&message=I+cannot+login](https://your-domain.com/submit.php?name=Mario+Rossi&email=mario@domain.com&category=2&subject=Login+Issue&message=I+cannot+login)

```

Supported query parameters: `name`, `email`, `subject`, `category` (or `category_id`), `message` (or `msg`).

---

## 📄 License

This project is open-source software licensed under the [MIT License](https://www.google.com/search?q=LICENSE).

```
