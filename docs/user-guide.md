# User guide

## Concepts
- **Client (workspace):** each customer we run email for. Everything — lists, campaigns, domains,
  forms, templates, websites, SMS — belongs to one client. Switch client from the top of the sidebar.
- **Team members** log in to the full app. **Administrators** also see Clients, Team and Settings.
- **Client portal users** (optional) see a read-only view of their own client's reports.

The **command palette** (Ctrl/⌘ + K) jumps to any page or action. The moon/sun button switches
dark and light themes.

## Home (dashboard)
Welcome summary, setup checklist, resource counters, 30-day performance (sent, open, click,
bounce), sending activity chart, deliverability health, recent and top campaigns.

## Audience

### Lists
**Audience → Lists → Create list:** name, default sender (from name/email, reply-to), postal
address (shown in footers), default sending server, double opt-in, welcome email and
unsubscribe notifications. Each list has tabs:
- **Overview** — subscriber headline, metric cards (open/click/subscribe/unsubscribe rates),
  setup checklist, composition, monthly and daily growth charts, quick actions, recent subscribers,
  email verification and settings summary.
- **Subscribers** — search, filter by status/tag/segment/verification, bulk actions (tag, untag,
  subscribe, unsubscribe, copy/move, blacklist, delete), CSV export, profile with activity timeline.
- **Segments** — match *all* or *any* conditions on fields, status, tags, dates and engagement
  (opened/clicked within N days, never opened). Counts update live.
- **Import** — upload CSV, map columns to fields, run in the background with progress and an error log.
- **Fields** — custom fields with merge tags: text, number, date, dropdown, checkbox and
  **phone number** (used for SMS).
- **Settings** — the list options above plus the **SMS phone field**.

### Overview & Subscribers (all lists)
**Audience → Overview** shows totals, health, growth and per-list engagement for the client.
**Audience → Subscribers** searches every contact across lists.

### Signup forms
**Audience → Forms:** embedded or popup forms that add contacts to a list.
- Embedded: copy the HTML code, or a hosted page link.
- Popup: centre modal, slide-in or top/bottom bar; triggers after a delay, on scroll, on exit intent
  or immediately; "don't show again for N days".
- Optional consent checkbox, thank-you message or redirect, honeypot and CAPTCHA against bots.
- Connect a form to a **website** so it loads automatically (see below).

### Websites
**Integration → Websites → Add website**, then paste the one-line script before `</body>`
and click **Connect** (it checks the code is live).
- Connected **popup** forms show automatically; **inline** forms render wherever you place
  `<div data-sm-form="FORM_ID"></div>`.
- Page views, unique visitors, top pages and **known contacts** (subscribers who arrived from an
  email link) are tracked. Works with WordPress, Joomla, Drupal, Shopify themes or plain HTML.

## Campaigns

### Email campaigns
**Campaigns → Email → Create campaign** opens a five-step wizard (sticky Save & continue bar):
1. **Recipients** — one or more lists/segments; exclude lists or segments. Duplicates and
   blacklisted addresses are removed automatically.
2. **Setup** — name, subject (AI suggestions available), preheader, sender, reply-to; advanced:
   open/click tracking, DKIM signing, spintax, skip failed messages, sending-server pool and
   weights, UTM tags, RSS feed.
3. **Content** — start from a template, the drag-and-drop builder, or HTML; merge tags such as
   `{{ SUBSCRIBER_FIRST_NAME | there }}`, `{{ UNSUBSCRIBE_URL }}`, `{{ SIGNATURE }}`; custom plain text.
4. **Schedule** — send now or later; optional sending window (business hours, no nights…).
5. **Confirm** — pre-flight checklist, **content score**, test email, launch.

A/B tests (subject, sender or content variants) send to a test group first and pick the winner
by opens or clicks. Campaigns can be paused, resumed, cancelled and duplicated.

### Reports
Each sent campaign has tabs:
- **Overview** — insight banner, recipients/delivered/failed/skipped/pending, rate summary, open
  and click detail, opens & clicks over time, email client/device/OS/country breakdowns, top links.
- **Links** — every clicked link with clicks, unique clicks and click rate.
- **Map** — opens and clicks by country.
- **Email review** — score out of 100: subject length, capitals, punctuation, preheader,
  spam-trigger words, unsubscribe link, Gmail clipping size, image alt text, text-to-image ratio,
  empty links and an optional broken-link check.
- **Logs** — delivery, open, click, bounce, complaint and unsubscribe logs.
- **SMS non-openers** — one click creates an SMS campaign to everyone who did not open.

### Automation
**Campaigns → Automation:** journeys that start on a trigger — new subscriber, API call,
unsubscribe, birthday/anniversary, date field, tag added/removed, field change, weekly, monthly
or new RSS post. Steps: **Send email**, **Send SMS**, **Wait**, **Condition** (opened, clicked,
has tag, field matches, still subscribed — with yes/no branches) and **Action** (tags, update
field, copy/move list, unsubscribe). Per-step stats and contacts list.

*Email-to-SMS tip:* Send email → Wait 2 days → Condition "Opened" → on **No**, Send SMS.

### Templates, signatures and assets
- **Templates** — starter layouts plus your own; edit in the builder or HTML.
- **Sending → Signatures** — reusable signatures; `{{ SIGNATURE }}` inserts the default one;
  builder blocks insert a copy.
- **Campaigns → Assets** — image and PDF library (shared with builder uploads); copy URLs.

### AI assistant
When configured by an administrator: subject line and preheader suggestions, rewrite and draft
content in the editor (Claude, OpenAI or a local Ollama model).

## SMS
See the [SMS guide](sms.md): bulk SMS campaigns, two-way inbox, SMS automation steps, SMS API.

## Client portal
Administrators can give a client read-only logins (**Client settings → Client portal access**, from the account menu). The
client sees only their own campaigns and reports.
