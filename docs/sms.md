# SMS

Bulk SMS campaigns, two-way conversations, SMS steps in automations (email-to-SMS) and an SMS API.

## 1. Connect a provider
**SMS → SMS providers**, pick a type:

| Provider | Best for | Two-way |
|---|---|---|
| **Africa's Talking** | Kenya (Safaricom, Airtel, Telkom), Uganda, Tanzania, Rwanda, Nigeria, Malawi, Zambia… | Yes |
| **Twilio** | Worldwide | Yes (Twilio numbers) |
| **Infobip** | Worldwide with strong African carrier coverage | Yes |
| **Vonage** | Worldwide | Yes |
| **Custom HTTP API** | Any local aggregator — Celcom Africa, Advanta, Onfon Media, Bulk SMS Kenya… | If the provider posts replies |
| **Test mode** | Trying campaigns for free (writes to the log) | — |

For each provider set a **sender ID** (registered alphanumeric sender such as `SCIAT` or a short
code — Kenyan networks require sender IDs to be registered through your provider) and a
**messages-per-second** limit. Use **Send a test SMS** to confirm it works. Credentials are
encrypted; secrets are never shown again.

### Delivery reports & replies
The provider page shows two URLs:
- **Delivery report URL** — Twilio, Infobip and Vonage receive it automatically with each message.
  For Africa's Talking paste it under *SMS → Callback URLs → Delivery reports*.
- **Incoming messages URL** — for replies: Africa's Talking *Incoming messages* callback (with a
  short code or two-way number), Twilio number "A message comes in", Infobip/Vonage inbound webhook.

### Custom HTTP API
Describe the provider's request: endpoint URL, format (form POST, JSON POST or GET), parameters
one per line with placeholders `{to}` (+2547…), `{to_plain}` (2547…), `{to_local}` (07…),
`{message}`, `{sender}`; optional headers, a success text and the JSON path of the message id.
Presets for **Celcom Africa** and **Advanta SMS** fill in their usual format — check the values
against your account documentation.

## 2. Phone numbers
- Add a list field of type **Phone number** (or name it PHONE/MOBILE). Choose it under the list's
  Settings → SMS if the list has several.
- Numbers are normalised to international format: `0712 345 678`, `712345678`, `254712345678`
  and `+254 712 345 678` all become `+254712345678`. The default country is set in
  Settings → Sending defaults (Kenya by default) and can be changed per client (Client settings).

## 3. Bulk SMS campaigns
**SMS → SMS campaigns → New SMS campaign:**
- **Message** with merge tags and a live counter: 160 characters per SMS (153 per part when
  longer); emoji or non-GSM characters switch to Unicode — 70 (67) per part.
- **Opt-out line** added at the end (default "Reply STOP to opt out").
- **Recipients:** a whole list, a segment, or **people who did not open an email campaign**.
  Only subscribed contacts with a valid number who have not opted out are included; one message
  per phone number.
- **Provider, sender ID, schedule.** Monthly SMS limits are respected.

The report shows recipients, sent, delivered, failed, delivery rate, opt-outs, SMS parts and a
per-number log. Campaigns can be paused, resumed or cancelled.

## 4. Two-way inbox
**SMS → SMS inbox** lists conversations with people who replied; reply directly from the thread.
Replies **STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT, ACHA, SITISHA** opt the number out of
SMS for that client; **START, UNSTOP, ANZA** opt back in.

## 5. SMS in automations (email-to-SMS)
Add a **Send SMS** step to any automation. Combine with a condition: *Send email → Wait 2 days →
Condition "Opened" → No → Send SMS* to reach people who ignore email.

## 6. SMS API
`POST /api/v1/sms` and `GET /api/v1/sms/{id}` — see [API](api.md). Webhook events
`sms.delivered`, `sms.failed` and `sms.received` notify your systems.
