# SCIAT Mail — Roadmap & Phase 1 Spec

Original mass-email platform for running client email programmes. Functional reference:
Acelle Mail feature list (`../MAIL.pdf`), acellesend.com/kb, acellesend.com/developers and
the public demo. No Acelle code is used — behaviour is re-implemented from scratch.

## Tenancy model
Only our team logs in. Each **client** is a **workspace**; every list, campaign, domain,
form, template and suppression entry belongs to one workspace. Team members switch
workspace from the sidebar. Sending servers can be shared (workspace = null) or
dedicated to a workspace.

## Phase 1 (this build)

### Audience
- Lists: name, sender identity (from name/email, reply-to), postal address (CAN-SPAM),
  default sending server, double opt-in, welcome email, unsubscribe notification.
- Custom fields per list: label, merge tag, type (text, number, date, dropdown,
  checkbox), default, required, visible, ordering. EMAIL is fixed.
- Subscribers: statuses subscribed / unconfirmed / unsubscribed / blacklisted, tags,
  activity timeline (sent, open, click, bounce, complaint, unsubscribe), bulk actions
  (tag, untag, subscribe, unsubscribe, copy/move to list, delete, blacklist).
- CSV import with column mapping, queued, progress + error log; CSV export.
- Segments: match all/any; conditions on email/custom fields (equals, not equals,
  contains, starts/ends with, blank, not blank, >, <), status, tag, created date,
  last open/click within N days, never opened.
- Suppression: workspace blacklist + global blacklist (hard bounces, complaints, manual).

### Campaigns
- Types: regular HTML, plain text.
- Wizard: Recipients (lists + optional segments, exclusions) → Setup (name, subject,
  preheader, from, reply-to, tracking opens/clicks, DKIM, skip failed, server pool with
  weights, UTM tags) → Content (template gallery, HTML code editor with live preview,
  merge tags, auto plain-text) → Schedule (now / date-time, sending window by weekday
  hours) → Confirm (pre-flight checklist, test email).
- Lifecycle: draft → scheduled → queued → sending → done; pause / resume / cancel; error.
- Engine: recipient snapshot (deduped by email, suppression removed), batched queue jobs,
  weighted server rotation, per-server hourly/daily throttles, retry transient failures,
  RFC 8058 one-click List-Unsubscribe headers, DKIM signing for SMTP.
- Duplicate campaign.

### Sending infrastructure
- Drivers: SMTP, Amazon SES (API + SMTP), SendGrid (API + SMTP), Mailgun (API + SMTP).
- Server test (send test email), status, quotas.
- Sending domains: verification TXT, generated DKIM key (2048-bit), SPF + DMARC checks,
  re-verify button.
- Tracking domains: CNAME to the app host, verified, used for open/click/unsubscribe links.

### Tracking & reports
- Open pixel, signed click redirects, unsubscribe page + one-click POST.
- Bounce/complaint webhooks: SES (SNS, signature validated), SendGrid event webhook,
  Mailgun (HMAC validated). Hard bounce → blacklist; complaint → unsubscribe + blacklist.
- Campaign report: delivered, failed, opens, clicks, CTOR, unsubscribes, bounces,
  complaints, top links, timeline chart; open/click/bounce/unsubscribe logs.
- Workspace dashboard: totals, 30-day activity, top campaigns, audience health.
- Sending health: per-workspace bounce/complaint rates with warning thresholds.

### Signup forms
- Embedded HTML form snippet + hosted subscribe page per list, honeypot + rate limit,
  optional consent checkbox, redirect URL, double opt-in confirmation flow.

## Phase 2 (next)
Automations (visual flow: subscribe/date/open/click/webhook triggers, delays, branches),
drag-and-drop email builder, A/B testing (subject/from/content, auto winner), popup
forms, email verification services, IMAP bounce/FBL handlers, IP warmup strategies,
spintax, RSS campaigns.

## Phase 3
REST API + outbound webhooks, client portal logins / roles, plans, quotas and billing,
white-label branding, AI assistant (subject lines, copy), GeoIP + device reports.
