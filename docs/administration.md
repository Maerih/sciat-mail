# Administration

Administrators see **Administration** in the sidebar: Clients, Team and Settings.

## Clients
Create, rename and delete clients (workspaces). Each client's **Client settings** (account menu)
hold timezone, company, postal address, website, brand colour/logo, SMS country, **limits**
(emails per month, SMS per month, contacts) and **client portal access** (read-only logins).

## Team
Add team members as *member* or *admin*. Members manage campaigns for every client; admins also
manage clients, the team, shared servers/providers and settings.

## Settings
| Tab | What it controls |
|---|---|
| **General** | Platform name, description, logo, favicon, accent colour, default theme, **form protection** (reCAPTCHA v2 / Cloudflare Turnstile), offline mode with message |
| **System mail** | SMTP / SendGrid / Amazon SES / Sendmail for password resets and notifications; default sender; test email |
| **Sending defaults** | One-click unsubscribe headers, global blacklist, defaults for new lists and campaigns, SMS default country and opt-out line |
| **Sending health** | Bounce/complaint monitoring per client: off, monitor or enforce (suspend + pause); limits and window; resume suspended clients |
| **AI assistant** | Claude, OpenAI or Ollama for subject lines and content |
| **Cron & queue** | Scheduler and queue status, cron command, failed jobs (retry / clear) |
| **Maintenance** | Apply uploaded updates, database backups (daily, manual, download), clear caches |
| **System info** | Versions, environment, PHP extensions, disabled functions, storage checks |

## Shared resources
Sending servers and SMS providers created by an administrator can be **shared** with every client.
Only administrators can change shared resources.

## Security notes
- Passwords are hashed; API tokens are stored as hashes; server, SMS, mailbox and AI credentials
  are encrypted with the app key. **Keep the `.env` file (APP_KEY) safe and backed up** — without it
  encrypted credentials cannot be read.
- The installer only exists while `APP_INSTALLER=true` and switches itself off.
- Forms have a honeypot and optional CAPTCHA; logins, forms, API and webhooks are rate limited.
