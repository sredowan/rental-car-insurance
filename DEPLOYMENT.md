# Rental Shield — Hostinger Deployment via Git + SSH

> Complete step-by-step guide to deploy the Rental Shield platform to Hostinger using Git with SSH keys.

---

## Table of Contents
1. [Prerequisites](#1-prerequisites)
2. [Generate SSH Key on Hostinger](#2-generate-ssh-key-on-hostinger)
3. [Add SSH Key to GitHub](#3-add-ssh-key-to-github)
4. [Connect Git Repository on Hostinger](#4-connect-git-repository-on-hostinger)
5. [Deploy the Code](#5-deploy-the-code)
6. [Configure the Database](#6-configure-the-database)
7. [Set Up Environment Variables](#7-set-up-environment-variables)
8. [Install Composer Dependencies](#8-install-composer-dependencies)
9. [Configure .htaccess & Routing](#9-configure-htaccess--routing)
10. [Setup Auto-Deployment (Webhook)](#10-setup-auto-deployment-webhook)
11. [Post-Deployment Checklist](#11-post-deployment-checklist)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Prerequisites

Before starting, ensure you have:

- [x] Hostinger **Business** or **Premium** hosting plan (with SSH access)
- [x] GitHub repository: `https://github.com/sredowan/rental-car-insurance.git`
- [x] Domain pointed to Hostinger DNS (`rentalshield.com.au`)
- [x] PHP 8.1+ enabled on Hostinger

### Verify PHP Version on Hostinger
1. Go to **hPanel** → **Websites** → **Manage**
2. Navigate to **Advanced** → **PHP Configuration**
3. Set PHP version to **8.1** or higher
4. Enable these extensions: `pdo_mysql`, `mbstring`, `json`, `openssl`, `gd`, `curl`

---

## 2. Generate SSH Key on Hostinger

### Step 2.1 — Open the Git Section
1. Log in to [hPanel](https://hpanel.hostinger.com)
2. Click **Websites** → select your website → **Manage**
3. In the left sidebar, search for **"GIT"** and click it

### Step 2.2 — Generate the SSH Key
1. Scroll to the **"SSH Key"** or **"Private Git Repository"** section
2. Click **Generate SSH Key**
3. **Copy the entire public key** that appears — you'll need it in the next step

> ⚠️ The key looks like: `ssh-rsa AAAAB3NzaC1yc2E... hostinger`

---

## 3. Add SSH Key to GitHub

### Step 3.1 — Open GitHub Deploy Keys
1. Go to your repo: https://github.com/sredowan/rental-car-insurance
2. Click **Settings** (top menu of the repo)
3. In the left sidebar, click **Deploy keys**

### Step 3.2 — Add the Key
1. Click **Add deploy key**
2. **Title**: `Hostinger Production Server`
3. **Key**: Paste the SSH public key copied from Hostinger
4. ☑️ Check **Allow write access** (optional, only needed if Hostinger pushes back)
5. Click **Add key**

> ✅ GitHub will now trust connections from your Hostinger server.

---

## 4. Connect Git Repository on Hostinger

### Step 4.1 — Create Repository Connection
1. Back in **hPanel** → **GIT** section
2. Find **"Create a New Repository"**
3. Fill in the fields:

| Field | Value |
|-------|-------|
| **Repository URL** | `git@github.com:sredowan/rental-car-insurance.git` |
| **Branch** | `main` |
| **Directory** | `public_html` |

> ⚠️ **Use SSH URL format** (`git@github.com:...`), NOT HTTPS. The SSH key won't work with HTTPS URLs.

> 💡 If deploying to a subdomain, use the subdomain's folder instead (e.g., `public_html/subdomain_folder`).

### Step 4.2 — Create
1. Click **Create**
2. Wait for Hostinger to validate the SSH connection to GitHub
3. The repository should appear in the **"Manage Repositories"** list

---

## 5. Deploy the Code

### Step 5.1 — Initial Deployment
1. In the **Manage Repositories** section, find your repo
2. Click the **Deploy** button (pull icon)
3. Wait for the deployment to complete — it pulls all files from `main` to `public_html`

### Step 5.2 — Verify Files
1. Go to **File Manager** in hPanel
2. Navigate to `public_html/`
3. Confirm you see:
   ```
   public_html/
   ├── index.html
   ├── api/
   │   ├── config/
   │   ├── admin/
   │   ├── auth/
   │   └── ...
   ├── css/
   ├── js/
   ├── assets/
   ├── .env.example
   └── ...
   ```

---

## 6. Configure the Database

### Step 6.1 — Create Database
1. Go to **hPanel** → **Databases** → **MySQL Databases**
2. Create a new database:
   - **Database name**: `rentalshield` (or your preferred name)
   - **Username**: choose a username
   - **Password**: generate a strong password
3. Note down the values — you'll need them for `.env`

### Step 6.2 — Import Schema
1. Go to **Databases** → **phpMyAdmin** → click **Enter phpMyAdmin**
2. Select your database
3. Click **Import** tab
4. Upload and run these SQL files **in order**:
   1. `database/schema.sql` — creates all tables + seeds default admin
   2. `database/migration_phase5.sql` — adds settings & audit_log tables

### Step 6.3 — Note the Database Host
- The DB host on Hostinger is typically: `localhost` or something like `srv1983.hstgr.io`
- Find it in **Databases** → **MySQL Databases** → listed under your database details

---

## 7. Set Up Environment Variables

### Step 7.1 — Create .env File via SSH

1. Go to **hPanel** → **Advanced** → **SSH Access**
2. Enable SSH if not already active
3. Note your **SSH credentials** (host, port, username)
4. Connect via terminal:
   ```bash
   ssh -p <PORT> <USERNAME>@<SSH_HOST>
   ```
5. Navigate to your site directory:
   ```bash
   cd public_html
   ```
6. Create the `.env` file:
   ```bash
   cp .env.example .env
   nano .env
   ```

### Step 7.2 — Fill in Production Values

```env
# App
APP_ENV=production
APP_URL=https://www.rentalshield.com.au

# Stripe (LIVE keys)
STRIPE_SECRET_KEY=sk_live_xxxxxxxxxxxxxxxxxxxxxx
STRIPE_PUBLISHABLE_KEY=pk_live_xxxxxxxxxxxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxxx

# Facebook / Meta (optional)
FB_PIXEL_ID=your_pixel_id
FB_CAPI_TOKEN=your_capi_token

# JWT — Generate with: openssl rand -hex 32
JWT_SECRET=your_64_char_random_string_here

# Database (from Step 6)
DB_HOST=localhost
DB_NAME=u538852360_rentalshield
DB_USER=u538852360_rentaluser
DB_PASS=YourStrongPassword123!
DB_PORT=3306

# SMTP Email (Hostinger)
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=info@rentalshield.com.au
MAIL_PASSWORD=YourEmailPassword
MAIL_FROM_EMAIL=info@rentalshield.com.au
MAIL_SUPPORT=info@rentalshield.com.au
```

7. Save: `Ctrl+O` → `Enter` → `Ctrl+X`

> ⚠️ **The `.env` file is gitignored** — it stays only on the server and is never pushed to GitHub.

### Step 7.3 — Alternative: Create via File Manager
If you prefer not to use SSH:
1. Go to **File Manager** → `public_html/`
2. Click **New File** → name it `.env`
3. Open it and paste the env variables above
4. Save

---

## 8. Install Composer Dependencies

### Via SSH Terminal:
```bash
cd public_html
php composer.phar install --no-dev --optimize-autoloader
```

> 💡 If `composer.phar` isn't deployed (it's in `.gitignore`), download it:
> ```bash
> curl -sS https://getcomposer.org/installer | php
> php composer.phar install --no-dev --optimize-autoloader
> ```

### What this installs:
- `firebase/php-jwt` — JWT authentication
- `stripe/stripe-php` — Stripe payment processing
- `phpmailer/phpmailer` — Email sending

---

## 9. Configure .htaccess & Routing

### Step 9.1 — Root .htaccess
Create/verify `public_html/.htaccess`:

```apache
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# API routing — send all /api/* requests to the API router
RewriteCond %{REQUEST_URI} ^/api/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

# Serve static files directly
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# SPA fallback — serve index.html for non-file requests
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.html [L]
```

### Step 9.2 — Protect Sensitive Directories
Create `public_html/api/config/.htaccess`:
```apache
<Files "*.php">
    # Only allow internal includes, deny direct browser access
    Order Deny,Allow
    Deny from all
</Files>
```

### Step 9.3 — Create Upload Directories
```bash
mkdir -p public_html/uploads/claims
mkdir -p public_html/uploads/policies
chmod 755 public_html/uploads -R
```

---

## 10. Setup Auto-Deployment (Webhook)

This makes every `git push` to `main` automatically deploy to Hostinger.

### Step 10.1 — Get Webhook URL
1. In **hPanel** → **GIT** → **Manage Repositories**
2. Find your repo and click **Auto Deployment** (or look for the webhook URL)
3. Copy the webhook URL (looks like `https://api.hostinger.com/deploy/xxxxx`)

### Step 10.2 — Add Webhook to GitHub
1. Go to https://github.com/sredowan/rental-car-insurance/settings/hooks
2. Click **Add webhook**
3. Fill in:

| Field | Value |
|-------|-------|
| **Payload URL** | The webhook URL from Hostinger |
| **Content type** | `application/json` |
| **Secret** | (leave blank unless Hostinger specifies one) |
| **Events** | Just the push event |

4. Click **Add webhook**

### Step 10.3 — Test
1. Make a small change locally
2. Commit and push:
   ```bash
   git add .
   git commit -m "test auto-deploy"
   git push origin main
   ```
3. Check Hostinger — the site should update automatically within 1-2 minutes

---

## 11. Post-Deployment Checklist

### Verify Everything Works

| Check | URL / Action | Expected Result |
|-------|-------------|-----------------|
| Homepage | `https://rentalshield.com.au` | Landing page loads |
| SSL | Check padlock icon | Valid SSL certificate |
| API Health | `https://rentalshield.com.au/api/` | Returns JSON response |
| Registration | `/register.html` | Can create account |
| Login | `/login.html` | OTP email received |
| Get Quote | Homepage quote form | Redirects to results |
| Checkout | `/checkout.html` | Stripe payment works |
| Dashboard | `/dashboard.html` | Shows user policies |
| Admin Login | `/admin-login.html` | Admin dashboard loads |
| File Upload | Submit a claim with image | Upload succeeds |
| PDF Download | Download a policy PDF | PDF generates correctly |
| Emails | Register / claim | Emails sent via SMTP |

### Security Checks
- [ ] `APP_ENV` is set to `production` (no error display)
- [ ] `.env` file is NOT accessible via browser
- [ ] `/api/config/` is NOT browsable
- [ ] `/vendor/` is NOT browsable
- [ ] Admin default password has been changed
- [ ] JWT_SECRET is a strong random string
- [ ] Stripe is using **live** keys (not test)

---

## 12. Troubleshooting

### "404 Not Found" on API endpoints
- Verify `.htaccess` rewrite rules are active
- Check **hPanel** → **Advanced** → **PHP Configuration** → ensure `mod_rewrite` is enabled

### "500 Internal Server Error"
- Check **hPanel** → **Advanced** → **Error Logs**
- Common causes:
  - Missing `vendor/` (run `composer install`)
  - Wrong PHP version (need 8.1+)
  - Database credentials incorrect in `.env`

### ".env not loading"
- The config.php has a built-in .env loader — verify the file exists at `public_html/.env`
- Check file permissions: `chmod 644 .env`

### "Stripe payments failing"
- Verify you're using **live** keys (not `sk_test_...`)
- Create webhook in Stripe Dashboard → point to `https://yourdomain.com/api/payments/confirm.php`
- Set `STRIPE_WEBHOOK_SECRET` in `.env`

### "Emails not sending"
- Verify SMTP credentials in `.env`
- Create the email account `info@rentalshield.com.au` in **hPanel** → **Emails**
- Test with: `MAIL_PORT=465` and `MAIL_ENCRYPTION=ssl`

### "Permission denied on uploads"
```bash
chmod 755 public_html/uploads -R
chown -R <user>:<user> public_html/uploads
```

---

## Quick Reference — Deployment Commands

```bash
# SSH into Hostinger
ssh -p <PORT> <USERNAME>@<SSH_HOST>

# Navigate to site
cd public_html

# Pull latest code manually (if webhook isn't set up)
git pull origin main

# Install/update dependencies
php composer.phar install --no-dev --optimize-autoloader

# Generate JWT secret
openssl rand -hex 32

# Check PHP version
php -v

# Test database connection
php api/test-db.php

# View error logs
tail -50 ~/logs/error.log
```

---

## Deployment Flow Summary

```
  LOCAL MACHINE                    GITHUB                      HOSTINGER
  ──────────────                   ──────                      ─────────
  code changes                        │                            │
       │                              │                            │
  git add + commit                    │                            │
       │                              │                            │
  git push origin main ──────────►  main branch                    │
       │                              │                            │
       │                         webhook fires ──────────────►  git pull
       │                              │                            │
       │                              │                     files deployed
       │                              │                     to public_html/
       │                              │                            │
       │                              │                      SITE IS LIVE ✅
```
