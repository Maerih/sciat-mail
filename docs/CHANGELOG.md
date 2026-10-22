# Changelog

## 1.0.0 — 22 October 2026

First complete release of SCIAT Mail.

### Platform
- Clients (workspaces) with per-client timezone, branding, SMS country and limits (emails, SMS, contacts).
- Team members and administrators; read-only client portal logins.
- Dark/light design modelled on the Acelle demo: sectioned sidebar, command palette (Ctrl/⌘ K),
  dashboards, section heroes, banners, pill tabs and wizard.
- White-label: platform name, logo, favicon, accent colour.

### Audience
- Lists with custom fields (text, number, date, dropdown, checkbox, phone), double opt-in,
  welcome emails, unsubscribe notifications.
- CSV import (background, mapping, error log) and export; bulk actions; activity timelines.
- Segments (all/any; fields, status, tags, dates, engagement).
- Audience overview and all-subscribers search; email verification (built-in, Emailable, Kickbox, ZeroBounce).
- Signup forms: embedded, hosted, popup (modal, slide-in, bars; delay/scroll/exit/immediate);
  consent, honeypot, reCAPTCHA v2 / Cloudflare Turnstile.
- Websites: one script for popup/inline forms, page-view tracking and known-contact visits.

### Email campaigns
- Five-step wizard with sticky action bar; A/B tests; spintax; RSS content; UTM tags; sending windows;
  server pools with weights; scheduling; pause/resume/cancel; duplicate.
- Drag-and-drop builder (GrapesJS) and HTML editor; starter templates; signatures (`{{ SIGNATURE }}`); assets library.
- AI assistant (Claude, OpenAI, Ollama) for subjects, preheaders and content.
- Reports: overview with insights, links, map, email review (content score, broken links), logs,
  device/client/OS/country breakdowns.

### Automation
- Triggers: subscribe, API, unsubscribe, birthday, date field, tag added/removed, field change,
  weekly, monthly, RSS.
- Steps: send email, **send SMS**, wait, condition (yes/no), actions.

### SMS
- Providers: Africa's Talking, Twilio, Infobip, Vonage, Custom HTTP API (Celcom Africa and Advanta presets), test mode.
- Bulk SMS campaigns with merge tags, GSM/Unicode segment counter, opt-out line, scheduling, delivery reports.
- Email-to-SMS (did-not-open audience, automation step, one-click from reports).
- Two-way inbox with STOP/ACHA opt-out and START opt-in.
- SMS API and `sms.*` webhook events; E.164 normalisation; monthly SMS limits.

### Sending & deliverability
- Sending servers: SMTP (incl. your own), Amazon SES, SendGrid, Mailgun (API/SMTP), Postmark,
  SparkPost, Elastic Email, Brevo, Mailjet, MailerSend, ZeptoMail.
- Sending domains with generated DKIM, SPF, DMARC and verification; tracking domains.
- Bounce/complaint webhooks and IMAP bounce mailboxes; blacklists; one-click unsubscribe headers.
- IP warmup strategies, domain warmup, self-hosted email warmup with seed inboxes and spam rescue,
  pre-warmed server flag.
- Sending health monitoring and enforcement; content review.

### Integrations & API
- Integrations overview page with live status.
- REST API v1 (lists, subscribers, campaigns, automations, transactional email, SMS) with token auth.
- Signed webhooks for subscriber, email, campaign and SMS events.

### Administration & hosting
- Settings hub: general, system mail, sending defaults, sending health, AI, cron & queue, maintenance, system info.
- Forgot-password flow; offline mode.
- Shared-hosting support: upload package, web installer, cron-driven queue, symlink-free uploads.
- Maintenance: in-app updates (migrations), daily database backups, cache clearing; update packages.
