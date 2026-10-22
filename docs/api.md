# API & webhooks

Base URL: `https://YOUR-DOMAIN/api/v1` · JSON in and out · 120 requests/minute per token.

**Authentication:** create a token under **Integration → API & webhooks** and send
`Authorization: Bearer sm_…`. A token only sees the client it belongs to. The in-app
**API reference** (help icon in the top bar) shows ready-to-copy curl commands with your real ids.

Errors: 401 bad token · 404 not found · 409 not allowed in the current state · 422 validation
(`{"message","errors":{field:[…]}}`) · 429 rate or quota limit.

## Endpoints
| Method | Path | Purpose |
|---|---|---|
| GET | `/me` | Token's client |
| GET, POST | `/lists` | List / create lists |
| GET, PUT/PATCH, DELETE | `/lists/{list}` | Read / update / delete a list |
| GET, POST | `/lists/{list}/subscribers` | List / add or update by email (`{"email","fields":{TAG:value},"tags":[…],"confirmed"?}`) |
| POST | `/lists/{list}/subscribers/bulk` | Add up to 1,000 subscribers |
| GET, PATCH, DELETE | `/lists/{list}/subscribers/{subscriber}` | Read / update / delete (uid or email) |
| POST | `/lists/{list}/subscribers/{subscriber}/unsubscribe` | Unsubscribe |
| POST | `/lists/{list}/subscribers/{subscriber}/tags` | Add/remove tags (`{"add":[…],"remove":[…]}`) |
| GET, POST | `/campaigns` | List / create draft campaigns |
| GET | `/campaigns/{campaign}` | Campaign with stats |
| POST | `/campaigns/{campaign}/launch` · `/pause` · `/resume` | Control sending |
| GET | `/automations` | Automations and run counts |
| POST | `/automations/{automation}/enroll` | Start an automation for a contact (`{"email":…}`) |
| POST | `/send` | Transactional email (html/text or template + data) |
| POST | `/sms` | Send one SMS (`{"to","message","sender?","provider?"}`) → 202 |
| GET | `/sms/{id}` | SMS status: queued, sent, delivered, failed |

### Examples
```bash
curl -X POST https://mail.example.com/api/v1/lists/LIST_UID/subscribers \
  -H "Authorization: Bearer sm_xxx" -H "Content-Type: application/json" \
  -d '{"email":"ann@example.com","fields":{"FIRST_NAME":"Ann","PHONE":"0712345678"},"tags":["vip"]}'

curl -X POST https://mail.example.com/api/v1/sms \
  -H "Authorization: Bearer sm_xxx" -H "Content-Type: application/json" \
  -d '{"to":"+254712345678","message":"Your code is 4821"}'
```

## Webhooks
Add endpoints under **API & webhooks** and choose events:
`subscriber.subscribed`, `subscriber.unsubscribed`, `email.opened`, `email.clicked`,
`email.bounced`, `email.complained`, `campaign.finished`, `sms.delivered`, `sms.failed`, `sms.received`.

Each POST carries `X-SM-Event`, `X-SM-Delivery` and `X-SM-Signature: sha256=<hmac>` — an
HMAC-SHA256 of the raw body with the endpoint's signing secret. Failed deliveries are retried with
backoff; endpoints that keep failing are disabled. Use "Webhooks by Zapier" or Make to connect
thousands of apps without code.

## Website script
`<script src="https://YOUR-DOMAIN/site/{website}/connect.js" async></script>` loads connected forms
and records page views (see the user guide).
