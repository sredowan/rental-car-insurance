# Project Memory

Last reviewed: 2026-04-29

## Purpose

This file is the working memory for the Rental Car Insurance project. Keep it updated after meaningful changes so future work starts with accurate project context.

## Project Overview

Rental Shield / DriveSafe Cover is a rental car insurance distributor website for Australia-first customers. It supports quote generation, customer registration/login, Stripe checkout, policy creation, PDF documents, claim submission, support requests, and admin management.

## Tech Stack

- Frontend: static HTML, vanilla CSS, vanilla JavaScript.
- Backend: PHP API routed through `api/index.php`.
- Database: MySQL via PDO singleton in `api/config/database.php`.
- Payments: Stripe PHP SDK.
- Email: PHPMailer via SMTP.
- PDFs: Dompdf.
- Deployment target: Hostinger PHP hosting.

## Important Paths

- `index.html`: public homepage and primary quote entry.
- `quote-result.html`: quote output and plan selection.
- `checkout.html`: payment checkout flow.
- `payment-success.html`: post-payment success screen.
- `dashboard.html`: customer dashboard.
- `dashboard-quote.html`: quote flow for logged-in users.
- `my-policies.html`: customer policy list/detail entry.
- `my-claims.html`: customer claim list/detail entry.
- `my-profile.html`: customer profile management.
- `admin-login.html`: admin login and OTP entry.
- `admin-dashboard.html`: admin KPI dashboard.
- `admin-quotes.html`: admin quote management.
- `admin-policies.html`: admin policy management.
- `admin-claims.html`: admin claim management.
- `admin-customers.html`: admin customer management.
- `admin-revenue.html`: admin revenue reports.
- `admin-settings.html`: admin pricing/settings/users.
- `admin-mailbox.html`: support/mailbox area.
- `admin-audit.html`: audit log viewer.
- `api/`: PHP backend source.
- `js/`: page-level frontend behavior and API client.
- `css/main.css`: main styling source.
- `database/`: schema and migrations.
- `assets/images/`: logo, favicon, hero and PDF images.
- `uploads/`: uploaded claim/support files.
- `vendor/`: Composer dependencies; do not manually edit.
- `dist/rentalshield-production/`: packaged production copy; source changes should normally happen at project root first.

## Frontend Structure

Public pages include `index.html`, `how-it-works.html`, `coverage.html`, `claims.html`, `about.html`, `faq.html`, `support.html`, `pds.html`, `privacy.html`, `terms.html`, `login.html`, `register.html`, `quote-result.html`, `checkout.html`, and `payment-success.html`.

Customer account pages include `dashboard.html`, `dashboard-quote.html`, `my-policies.html`, `my-claims.html`, and `my-profile.html`.

Admin pages include `admin-login.html`, `admin-dashboard.html`, `admin-quotes.html`, `admin-policies.html`, `admin-claims.html`, `admin-customers.html`, `admin-revenue.html`, `admin-settings.html`, `admin-mailbox.html`, and `admin-audit.html`.

Frontend JavaScript files in `js/` are page-specific. `js/api.js` is the central API client and also contains large portions of public quote/result flow behavior.

## API Structure

All `/api/*` requests are routed by `api/index.php`. The local PHP dev router is `router.php` and can serve the project with:

```bash
php -S localhost:8765 router.php
```

Primary API route groups:

- `api/auth/`: customer auth, OTP, forgot password.
- `api/admin/`: admin login, OTP, dashboards, quotes, policies, claims, customers, users, settings, revenue, audit, mailbox.
- `api/quotes/`: quote creation and retrieval.
- `api/policies/`: policy creation, listing, and PDF access.
- `api/claims/`: claim submission and claim listing/detail.
- `api/payments/`: Stripe publishable config, PaymentIntent creation, payment confirmation.
- `api/profile/`: customer profile operations.
- `api/support/`: support request handling.
- `api/helpers/`: JWT, response helpers, mailer, PDF, invoice PDF, tracking.
- `api/middleware/`: auth, CORS, security headers/rate limiting.
- `api/config/`: environment config and database connection.

Health endpoints are handled inline by `api/index.php` at `/api/health` and `/api/ping`.

## Authentication Notes

- JWT settings are defined in `api/config/config.php`.
- Customer/admin token is sent by frontend as `Authorization: Bearer {token}`.
- Frontend auth storage keys are `dsc_token`, `dsc_user`, `dsc_is_admin`, and sometimes `dsc_quote`.
- Admin login uses OTP routing through `admin/login` and `admin/otp`.
- Customer OTP support exists through `auth/send-otp` and `auth/verify-otp`.

## Payment Notes

- Stripe keys come from `.env` through `api/config/config.php`.
- Expected env keys: `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, and optionally `STRIPE_WEBHOOK_SECRET`.
- Payment APIs are routed to `api/payments/config.php`, `api/payments/create-intent.php`, and `api/payments/confirm.php`.
- Currency is fixed to AUD by `STRIPE_CURRENCY`.
- Never commit real Stripe keys or webhook secrets.

## Database Structure

Main schema file: `database/schema.sql`.

Primary tables:

- `customers`: customer account, contact, status, OTP fields.
- `admins`: admin accounts, roles, OTP fields.
- `quotes`: quote requests and conversion status.
- `policies`: issued policies, payment status, PDF URL, cancellation state.
- `claims`: customer claims and admin claim workflow.
- `claim_documents`: uploaded claim documents.
- `password_resets`: password reset token hashes.
- `audit_logs`: admin action audit records.
- `coverage_pricing`: admin-editable coverage pricing.
- `site_settings`: editable site-level settings.

Additional migrations:

- `database/migration_customer_otp.sql`: customer OTP columns.
- `database/migration_phase5.sql`: legacy/additional settings and audit tables.
- `database/update_vehicle_types.sql`: vehicle type columns for quotes and policies.

Views:

- `v_policy_summary`: policy/customer/claim summary.
- `v_claim_summary`: claim/customer/policy summary.

## Environment Configuration

Example file: `.env.example`.

Important env keys:

- `APP_ENV`
- `APP_URL`
- `STRIPE_SECRET_KEY`
- `STRIPE_PUBLISHABLE_KEY`
- `STRIPE_WEBHOOK_SECRET`
- `FB_PIXEL_ID`
- `FB_CAPI_TOKEN`
- `JWT_SECRET`
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_PORT`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_ENCRYPTION`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM_EMAIL`
- `MAIL_SUPPORT`

Do not expose or commit `.env` secrets.

## Composer Dependencies

Defined in `composer.json`:

- `stripe/stripe-php`
- `phpmailer/phpmailer`
- `dompdf/dompdf`

Do not manually edit files under `vendor/`.

## Deployment Notes

Deployment guide: `DEPLOYMENT.md`.

Target deployment is Hostinger with PHP 8.1+, MySQL, Composer dependencies, and Apache/LiteSpeed routing. Import database files in the documented order. Production `.env` lives on the server and should not be committed.

## SEO Research MCP

The project can use the community Keywords Everywhere MCP server for SEO keyword, competitor, traffic, and backlink research.

Repository: `https://github.com/hithereiamaliff/mcp-keywords-everywhere`

Important tools:

- `get_credits`: verify account credits before research.
- `get_keyword_data`: volume, CPC, competition for target keywords.
- `get_related_keywords`: related terms from seed keywords.
- `get_pasf_keywords`: People Also Search For keyword ideas.
- `get_domain_keywords`: competitor domain ranking keywords.
- `get_domain_traffic`: competitor traffic estimates.
- `get_domain_backlinks`: backlink research.

Primary Rental Shield keyword seeds:

- `rental car excess insurance australia`
- `car hire excess insurance australia`
- `rental vehicle excess cover`
- `hire car insurance australia`
- `rental car insurance australia`
- `rental car damage waiver alternative`
- `domestic car rental excess insurance`
- `international car hire excess insurance`

Competitor domains to analyze:

- `rentalcover.com`
- `carhireexcess.com.au`
- `rentalvehicleexcess.com.au`
- `tripcover.com.au`

Security rule: never store the Keywords Everywhere API key in this repository, `MEMORY.md`, `.env`, or committed MCP config. Store it only in the local environment variable `KEYWORDS_EVERYWHERE_API_KEY`.

OpenCode project config is stored in `opencode.json` and uses the local MCP server through `npx`:

```json
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "keywords-everywhere": {
      "type": "local",
      "command": ["npx", "-y", "mcp-keywords-everywhere"],
      "enabled": true,
      "environment": {
        "KEYWORDS_EVERYWHERE_API_KEY": "{env:KEYWORDS_EVERYWHERE_API_KEY}"
      }
    }
  }
}
```

## Source Of Truth Rules

- Treat root HTML/CSS/JS/PHP files as source.
- Treat `dist/rentalshield-production/` as generated/package output unless specifically asked to update deployment output.
- Avoid changing `vendor/` directly.
- Avoid changing `.env` unless explicitly requested and never reveal secrets in summaries.
- If API routes change, update `api/routes.md` and this file.
- If database tables/columns change, update `database/` migrations/schema notes and this file.

## Local SEO / Marketing Skills

Useful no-credit SEO skill source lives outside this repo at:

`C:\Users\ADMIN\OneDrive\Documents\PERSONAL\DEVELOPMENTS\PTE VERSE\tmp\claude-skills`

Use these for future Rental Shield SEO, content, and domain research work:

- `marketing-skill/seo-audit/SKILL.md`: technical/on-page SEO audit workflow.
- `marketing-skill/seo-audit/references/seo-audit-reference.md`: crawlability, indexation, title/meta, heading, image, internal link, and E-E-A-T checks.
- `marketing-skill/seo-audit/scripts/seo_checker.py`: local HTML SEO checker. Run from this repo with `py "C:\Users\ADMIN\OneDrive\Documents\PERSONAL\DEVELOPMENTS\PTE VERSE\tmp\claude-skills\marketing-skill\seo-audit\scripts\seo_checker.py" --file "index.html" --json`.
- `marketing-skill/schema-markup/SKILL.md`: JSON-LD schema audit/implementation workflow.
- `marketing-skill/schema-markup/scripts/schema_validator.py`: local schema validator. On Windows, set UTF-8 output first: `$env:PYTHONIOENCODING='utf-8'`.
- `marketing-skill/site-architecture/SKILL.md`: URL hierarchy, sitemap, nav, hub/spoke, and internal linking strategy.
- `marketing-skill/site-architecture/references/internal-linking-playbook.md`: anchor text and topic cluster linking rules.
- `marketing-skill/programmatic-seo/SKILL.md`: page-at-scale strategy for location, comparison, glossary, vehicle-type, and guide pages.
- `marketing-skill/content-strategy/SKILL.md`: content pillar, topic cluster, and competitor content gap workflow.
- `marketing-skill/ai-seo/SKILL.md`: AI search / citation optimization, extractable answer blocks, FAQ blocks, comparison tables, and AI crawler checks.
- `product-team/landing-page-generator/references/seo-checklist.md`: landing page SEO checklist for meta, schema, Core Web Vitals, keyword placement, internal links, image optimization, canonicals, and mobile checks.

Current preferred no-credit SEO workflow for Rental Shield:

1. Use `seo_checker.py` on public pages before/after edits.
2. Check `robots.txt`, `sitemap.xml`, canonical tags, `noindex` rules, OG/Twitter tags, and JSON-LD.
3. Use `rental car excess insurance australia` as the primary commercial theme unless live search data suggests otherwise.
4. Use competitor language from accessible public pages when paid keyword credits are unavailable.
5. Use `programmatic-seo` only for pages with unique value, not thin keyword swaps.
6. Use `ai-seo` patterns on guide/FAQ pages: definition blocks, numbered steps, comparison tables, direct Q&A, and schema.

## Change Log

### 2026-04-29 - Saved Local SEO Skill References

- Documented useful local SEO/marketing skill files from `PTE VERSE\tmp\claude-skills` for future Rental Shield work.
- Saved local command examples for SEO and schema validation without paid keyword credits.
- No application code changed.

### 2026-04-29 - Added OpenCode Keywords Everywhere MCP Config

- Added `opencode.json` with a local `keywords-everywhere` MCP server using `npx -y mcp-keywords-everywhere`.
- Config reads `KEYWORDS_EVERYWHERE_API_KEY` from the local environment and does not store the actual API key.
- Updated MCP memory notes from generic MCP config to OpenCode's `mcp` format.
- No application code changed.

### 2026-04-29 - Documented SEO MCP Workflow

- Added Keywords Everywhere MCP workflow, target keyword seeds, competitor domains, and secret-handling rules.
- Did not store the provided API key in project files.
- No application code changed.

### 2026-04-29 - Created Project Memory

- Created `MEMORY.md` with analyzed site structure, API map, database summary, payment/auth notes, deployment notes, and maintenance rules.
- No application code changed.

## Update Checklist For Future Changes

After every meaningful change, update this file with:

- Date of change.
- Files changed.
- What changed.
- Why it changed.
- API impact, if any.
- Database impact, if any.
- Payment/auth/security impact, if any.
- Verification performed.
- Follow-up risks or TODOs.
