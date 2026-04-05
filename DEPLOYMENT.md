# DriveSafe Cover — Production Deployment Checklist

## Pre-Deployment

### 1. Environment Configuration
- [ ] Update `api/config/config.php`:
  - [ ] Set `APP_ENV` to `'production'`
  - [ ] Set `APP_URL` to `'https://drivesafecover.com.au'`
  - [ ] Change `JWT_SECRET` to a unique 64-char random string
  - [ ] Update database credentials if different from dev
  - [ ] Set live Stripe keys (`STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`)
  - [ ] Set `STRIPE_WEBHOOK_SECRET` after creating webhook in Stripe Dashboard
  - [ ] Configure SMTP settings with Hostinger email
  
### 2. Database
- [ ] Run `database/schema.sql` on production database
- [ ] Run `database/migration_phase5.sql` for settings + audit_log tables
- [ ] Verify the default super admin seed in `schema.sql` (change password!)
- [ ] Create `uploads/claims/` and `uploads/policies/` directories with proper permissions

### 3. Security
- [ ] Ensure `APP_ENV = 'production'` (disables error display)
- [ ] Set `.htaccess` to deny direct access to `/api/config/`, `/vendor/`
- [ ] Verify CORS middleware only allows your domain
- [ ] Generate a strong `JWT_SECRET` (use: `openssl rand -hex 32`)
- [ ] Change default admin password immediately after first login
- [ ] Ensure `uploads/` directory is NOT publicly browsable (add `.htaccess`)

### 4. Stripe
- [ ] Switch to live API keys in config
- [ ] Create a Stripe webhook for `payment_intent.succeeded`
- [ ] Set `STRIPE_WEBHOOK_SECRET` from the Stripe dashboard
- [ ] Test a payment with a live card

### 5. DNS & Hosting
- [ ] Point `drivesafecover.com.au` DNS to Hostinger
- [ ] Install SSL certificate (Let's Encrypt / Hostinger auto-SSL)
- [ ] Set document root to the project directory
- [ ] Verify PHP 8.1+ is installed on the server
- [ ] Ensure `php-pdo`, `php-mbstring`, `php-json`, `php-openssl`, `php-gd` extensions enabled

### 6. Upload Files
- [ ] Upload all project files via FTP or Git
- [ ] Run `composer install --no-dev --optimize-autoloader` on server
- [ ] Set directory permissions: `chmod 755 uploads/ -R`

---

## Post-Deployment Verification

- [ ] Visit `https://drivesafecover.com.au/api/health` — should return database connected
- [ ] Test customer registration flow
- [ ] Test login and dashboard
- [ ] Test quote generation
- [ ] Test Stripe checkout with live card
- [ ] Test claim submission with file upload
- [ ] Test admin login (admin@drivesafecover.com.au)
- [ ] Test admin claims management
- [ ] Verify PDF policy download works
- [ ] Check emails are being sent (policy confirmation, claim updates)
- [ ] Verify rate limiting on auth endpoints

---

## Hostinger-Specific Notes
- PHP version: Use Hostinger panel to set PHP 8.1+
- Document root: Set to `/public_html/` or your custom domain folder
- SMTP: Use `smtp.hostinger.com` port 465 (SSL) or 587 (TLS)
- Database: Use the phpMyAdmin panel to import SQL files
- Cron: Set up a daily cron to expire policies: `php /path/to/api/cron/expire-policies.php`
