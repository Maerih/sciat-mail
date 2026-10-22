# Sending & deliverability

## Sending servers
**Sending → Sending servers → Add server.** Supported:

| Service | Type | Bounces/complaints |
|---|---|---|
| Your own SMTP server / any SMTP | SMTP | Bounce handler mailbox (IMAP) |
| Amazon SES | API or SMTP | Webhooks (SNS) |
| SendGrid | API or SMTP | Webhooks |
| Mailgun | API or SMTP | Webhooks |
| Postmark, SparkPost, Elastic Email, Brevo, Mailjet, MailerSend, ZeptoMail | SMTP relay presets | Bounce handler mailbox |

Per server: hourly and daily limits, default from address, bounce address, IP warmup strategy,
**pre-warmed** flag, test email. Servers can be shared with every client (administrators) or
dedicated to one client. Campaigns rotate messages across servers by weight.

### Using your own SMTP server
Add server → **SMTP**: host, port (587 STARTTLS or 465 SSL), username, password → **Test**.
Then add a **bounce handler** (Sending → Bounce handlers) for the mailbox that receives bounces
so hard bounces and complaints are suppressed automatically.

## Sending domains (DKIM, SPF, DMARC)
**Sending → Sending domains → Add domain.** The platform generates:
- **Ownership** TXT record,
- **DKIM** key (TXT at `<selector>._domainkey`) — SMTP mail is signed with it,
- **SPF** (`v=spf1` including your servers' providers),
- **DMARC** (`_dmarc` TXT).

Publish them at your DNS provider and click **Verify**. Campaigns warn when the sender's domain
is not verified.

**Tracking domains** put open/click/unsubscribe links on your own domain (CNAME to the platform).

## Warmup
| Feature | Where | What it does |
|---|---|---|
| **IP warmup** | Sending → Warmup strategies, then pick a strategy on the server | Linear or exponential daily quota from an initial to a target volume |
| **Domain warmup** | Sending domain page → Domain warmup | Caps how many emails a domain sends per day across all servers |
| **Email warmup service** | Sending → Email warmup | Sends 4/day growing by 3/day (to your max) to seed inboxes you own between 08:00 and 20:00; every 15 minutes the seed inboxes are checked over IMAP, emails in spam are moved to the inbox and all are marked read and important. Shows inbox placement over 14 days |
| **Pre-warmed SMTP server** | Server settings → Pre-warmed | Skips warmup limits for servers that already have a reputation |

Seed inbox tips: 3–10 mailboxes across Gmail, Outlook, Yahoo and your own domain; Gmail, Yahoo
and Outlook need an *app password* with IMAP enabled.

## List hygiene
- **Email verification** (Sending → Email verification): built-in check (syntax, MX, disposable,
  role addresses) or Emailable, Kickbox, ZeroBounce. Verify a whole list from its overview;
  undeliverable addresses are skipped by every campaign.
- **Blacklist**: per client plus a global list; hard bounces and complaints are added automatically.
- **One-click unsubscribe** headers (RFC 8058) on every campaign (Gmail/Yahoo bulk-sender rules).

## Sending health (administrators)
**Settings → Sending health** measures each client's hard-bounce and complaint rates over a
rolling window. *Monitor* flags clients over the limits; *Enforce* suspends the client and pauses
running campaigns until an administrator reviews and resumes sending.

## Limits
Administrators can set per-client monthly email, monthly SMS and contact limits (**Client settings → Limits**).
