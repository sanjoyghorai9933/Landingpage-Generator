# Landing Page Generator — Self-Hosted Backend

Turns a form submission into a ready-to-use landing page folder (index.html,
crm_connect.php, thanks.html, assets/) zipped up with a download link —
no n8n, no third-party workflow tool. Just PHP on your own hosting.

## Folder structure

```
backend/
├── generate.php                  ← the whole backend, one file
├── templates/                    ← protected, not web-accessible
│   ├── index-template.html       ← your site's HTML with {{TOKENS}}
│   ├── crm_connect-template.php  ← enquiry form handler with {{TOKENS}}
│   ├── config_smtp-template.php  ← your SMTP sender credentials (fixed)
│   ├── SMTPMailer.php            ← unchanged mailer class
│   └── thanks.html               ← unchanged thank-you page
├── assets/                       ← YOUR real css/js/fonts/default images go here
└── output/
    ├── submissions.csv           ← auto-created log of every request
    └── zips/                     ← generated .zip files land here (public)
```

## Live deployment (luxury-residences.online, GoDaddy cPanel)

This copy is already configured for:
```
https://luxury-residences.online/LP-Generator/
```

1. **Upload the whole `LP-generator/` folder** (the parent of this
   `backend/` folder) via cPanel → **File Manager** (or FTP) into
   `public_html/LP-Generator/` — the folder name must match exactly
   (Linux hosting is case-sensitive), so the final structure is:
   ```
   public_html/LP-Generator/index.html
   public_html/LP-Generator/landing-page-request.html   (same file, alt name)
   public_html/LP-Generator/backend/generate.php
   public_html/LP-Generator/backend/templates/...
   public_html/LP-Generator/backend/assets/...
   ```
   If you upload a `.zip`, use File Manager's **Extract** action rather
   than uploading files one by one.

2. **Fill in `backend/assets/`** with your real, unchanging files —
   `css/style.css`, `js/app.js`, fonts, and one default image per slot
   (`slider1.jpg`, `gallery1.jpg`, etc.). These act as fallback images if
   a visitor skips an upload. (This project already ships with sample
   assets; swap in your own when ready.)

3. **Set your SMTP sender details** in `backend/templates/config_smtp-template.php`
   — this is the *sending* account (e.g. `leads@luxury-residences.online`),
   same for every generated page. It's separate from the "Lead Email" field
   in the form, which is the *recipient* address and does change per project.
   GoDaddy cPanel hosting normally lets you send mail from an address on
   your own domain without extra SMTP setup — check **cPanel → Email
   Accounts** if `leads@luxury-residences.online` (or whichever address you
   use) doesn't exist yet.

4. **Check folder permissions** — cPanel File Manager usually sets new
   folders to `755` by default, which is enough for `backend/output/` to be
   created and written to on the first form submission. If a submission
   fails with a permissions error, right-click `backend/` in File Manager →
   **Change Permissions** → set `output` to `755` (try `775` if `755`
   doesn't work on your specific plan).

5. **Confirm PHP version & ZipArchive** — in cPanel, go to **Select PHP
   Version** (sometimes called **MultiPHP Manager**) and make sure
   `LP-Generator/` (or the domain/subdomain it's under) is set to PHP
   7.4 or newer, with the `zip` extension enabled (it's enabled by
   default on virtually all GoDaddy PHP versions).

6. **Webhook URL is already set** — `index.html` /
   `landing-page-request.html` point at:
   ```js
   const WEBHOOK_URL = "https://luxury-residences.online/LP-Generator/backend/generate.php";
   ```
   Only change this if you move the backend to a different path or domain.

7. **First submission auto-creates `backend/output/`** (zips, previews,
   submissions.csv) along with the `.htaccess` files that keep it locked
   down — no manual step needed. Just submit one test request through the
   form to confirm everything works end-to-end.

That's it. No database, no cron job, no build step.

## How a request flows through `generate.php`

1. Validates the required fields (project name, address, phone, lead email,
   price range, and the first slider image) — rejects with a clear error
   if anything's missing.
2. Creates a temporary working folder named after the project + timestamp.
3. Copies your static `assets/` folder in first (so nothing's ever missing).
4. Saves each uploaded image over the matching default filename
   (`slider1.jpg`, `gallery2.jpg`, `logo.png`, etc.).
5. Builds the dynamic bits — highlight strip, pricing table rows, one
   floor-plan card per pricing row, gallery grid (only for photos actually
   uploaded), and the Google Maps embed — then fills every `{{TOKEN}}` in
   `index-template.html`.
6. Fills `crm_connect-template.php` with the lead's To/CC email and project
   name, so enquiries from *that* generated page go to the right inbox.
7. Zips the whole folder (`index.html`, `thanks.html`, `crm_connect.php`,
   `config_smtp.php`, `SMTPMailer.php`, `assets/…`) into
   `output/zips/<project-slug>-<timestamp>.zip`.
8. Deletes the temporary working folder (the zip already has everything).
9. Logs the submission to `output/submissions.csv` for your own records.
10. Responds with JSON: `{ "success": true, "downloadLink": "https://..." }`
    — this is what the web app reads to show the Download button instantly.

## Recently added: Location Advantages, About Builder, Analytics
- **Location Advantages** — an optional bullet list rendered right below
  the Google Maps embed in the Location section. Skipped entirely if no
  advantages are entered.
- **About Builder** — an optional nav tab + section (previously present
  in the template but commented out and hardcoded to a different client's
  copy). It only appears when you fill in the About Text field on the
  request form; leaving it blank removes both the nav tab and the section,
  no gap left behind.
- **Analytics & Conversion Tracking** — Google gtag.js + Google Ads
  conversion tracking, now a per-project setting instead of a hardcoded ID.
  **Important:** earlier versions of this generator shipped every page with
  a stray hardcoded Google Ads tag (`AW-975152938`) baked into both
  `index.html` and `thanks.html`. That's been removed — a freshly generated
  page now ships with **no tracking code at all** unless you fill in the
  Tag ID and/or Conversion "Send To" ID on the request form. If you were
  relying on that old default, you'll need to explicitly set your own ID
  going forward.

## Security notes worth knowing
- `templates/.htaccess` blocks direct web access to that folder (so nobody
  can browse to your SMTP password). `output/.htaccess` and
  `output/zips/.htaccess` do the same for the output folder — these two are
  written automatically by `generate.php` the first time it runs (since
  `output/` doesn't exist until the first submission), so no manual step
  is needed on a fresh deploy.
- If your host uses Nginx instead of Apache, `.htaccess` files are ignored —
  add equivalent `deny all;` rules for `/templates/` and `/output/` (excluding
  `/output/zips/`) in your server block instead.
- Only `.jpg`, `.jpeg`, `.png`, and `.webp` uploads are accepted; anything
  else is silently skipped rather than saved.
- Consider adding a simple shared-secret header check in `generate.php` if
  the form will be public, so random visitors can't spam-generate zips.

## Troubleshooting
- **"Could not create working directory"** → `output/` isn't writable; check
  permissions.
- **Blank/500 response** → check your host's PHP error log; almost always
  a missing `ZipArchive` extension or a file-permission issue.
- **Download link 404s** → confirm `output/zips/.htaccess` uploaded correctly
  and that your host allows `.htaccess` overrides (`AllowOverride All`).
