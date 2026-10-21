<?php

namespace App\Sending;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\SendingDomain;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Services\RssFeed;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

/**
 * Turns a campaign + delivery into a ready-to-send MIME message: merge tags, compliance
 * footer, link/open tracking, List-Unsubscribe headers and DKIM signature.
 */
class MessageBuilder
{
    public function __construct(protected TrackingUrls $urls) {}

    public function forCampaign(Campaign $campaign, Delivery $delivery, ?Subscriber $subscriber, SendingServer $server): Message
    {
        $campaign = $this->spun($campaign, $delivery);
        $values = $this->values($campaign, $delivery, $subscriber);
        $subject = MergeTags::render((string) $campaign->subject, $values, false);

        $email = (new Email)
            ->from(new Address($campaign->from_email, $campaign->from_name ?? ''))
            ->to($delivery->email)
            ->subject($subject);

        if ($campaign->reply_to) {
            $email->replyTo($campaign->reply_to);
        }

        if ($campaign->isPlain()) {
            $email->text($this->ensureTextFooter(MergeTags::render((string) $campaign->plain, $values, false), $values));
        } else {
            $email->html($this->renderHtml($campaign, $delivery, $values));
            // Text part comes from the untracked body so it carries no preheader filler or pixel
            $email->text($campaign->plain
                ? $this->ensureTextFooter(MergeTags::render($campaign->plain, $values, false), $values)
                : HtmlToText::convert($this->ensureHtmlFooter(MergeTags::render((string) $campaign->html, $values), $values)));
        }

        $this->addHeaders($email, $delivery, $values['UNSUBSCRIBE_URL']);

        return $this->sign($email, $campaign->workspace_id, $server, $campaign->sign_dkim);
    }

    /** Non-campaign mail (test sends, opt-in confirmations, notifications). */
    public function simple(string $fromEmail, ?string $fromName, string $to, string $subject, string $html, int $workspaceId,
        SendingServer $server, ?Delivery $delivery = null): Message
    {
        $email = (new Email)
            ->from(new Address($fromEmail, $fromName ?? ''))
            ->to($to)
            ->subject($subject)
            ->html($html)
            ->text(HtmlToText::convert($html));

        if ($delivery) {
            $email->getHeaders()->addTextHeader('X-SM-Delivery', $delivery->uid);
            $email->getHeaders()->add(new MetadataHeader('delivery', $delivery->uid));
        }

        return $this->sign($email, $workspaceId, $server, true);
    }

    public function values(Campaign $campaign, Delivery $delivery, ?Subscriber $subscriber): array
    {
        $list = $subscriber?->list;

        return MergeTags::subscriberValues($subscriber) + [
            'SUBSCRIBER_EMAIL' => $delivery->email,
            'UNSUBSCRIBE_URL' => $this->urls->unsubscribe($delivery),
            'WEB_VIEW_URL' => $this->urls->webView($delivery),
            'LIST_NAME' => $list?->name ?? '',
            'CAMPAIGN_NAME' => $campaign->name,
            'CONTACT_ADDRESS' => $list?->postalAddress() ?? (string) $campaign->workspace->address,
            'CURRENT_YEAR' => date('Y'),
        ] + app(RssFeed::class)->mergeValues($campaign->rssConfig());
    }

    /** Unsaved copy with spintax expanded for this recipient (no-op when spintax is off). */
    public function spun(Campaign $campaign, Delivery $delivery): Campaign
    {
        if (! $campaign->spintax) {
            return $campaign;
        }

        $copy = clone $campaign;
        foreach (['subject', 'preheader', 'html', 'plain'] as $field) {
            if (filled($copy->{$field})) {
                $copy->{$field} = Spintax::spin($copy->{$field}, $delivery->uid.':'.$field);
            }
        }
        $copy->spintax = false; // already expanded

        return $copy;
    }

    public function renderHtml(Campaign $campaign, Delivery $delivery, array $values, bool $track = true): string
    {
        $campaign = $this->spun($campaign, $delivery);
        $html = MergeTags::render((string) $campaign->html, $values);
        $html = $this->ensureHtmlFooter($html, $values);

        if ($campaign->preheader) {
            $html = $this->injectPreheader($html, MergeTags::render($campaign->preheader, $values));
        }

        // Our own unsubscribe / web-view links are never UTM-tagged or click-tracked
        $ownLinks = [$values['UNSUBSCRIBE_URL'], $values['WEB_VIEW_URL']];

        if (! empty($campaign->utm['enabled'])) {
            $html = $this->rewriteLinks($html, fn ($url) => in_array($url, $ownLinks, true) ? $url : $this->appendUtm($url, $campaign->utm));
        }

        if ($track && $campaign->track_click) {
            $html = $this->rewriteLinks($html, fn ($url) => in_array($url, $ownLinks, true) ? $url : $this->urls->click($delivery, $url));
        }

        if ($track && $campaign->track_open) {
            $pixel = '<img src="'.e($this->urls->open($delivery)).'" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px">';
            $html = stripos($html, '</body>') !== false ? str_ireplace('</body>', $pixel.'</body>', $html) : $html.$pixel;
        }

        return $html;
    }

    /** CAN-SPAM / GDPR: every marketing email needs an unsubscribe link and postal address. */
    protected function ensureHtmlFooter(string $html, array $values): string
    {
        if (str_contains($html, $values['UNSUBSCRIBE_URL'])) {
            return $html;
        }

        $footer = '<div style="font-family:Arial,sans-serif;font-size:12px;color:#888;text-align:center;padding:24px 12px">'
            .($values['CONTACT_ADDRESS'] ? e($values['CONTACT_ADDRESS']).'<br>' : '')
            .'<a href="'.e($values['UNSUBSCRIBE_URL']).'" style="color:#888">Unsubscribe</a></div>';

        return stripos($html, '</body>') !== false ? str_ireplace('</body>', $footer.'</body>', $html) : $html.$footer;
    }

    protected function ensureTextFooter(string $text, array $values): string
    {
        if (str_contains($text, $values['UNSUBSCRIBE_URL'])) {
            return $text;
        }

        return rtrim($text)."\n\n--\n".($values['CONTACT_ADDRESS'] ? $values['CONTACT_ADDRESS']."\n" : '')
            .'Unsubscribe: '.$values['UNSUBSCRIBE_URL'];
    }

    protected function injectPreheader(string $html, string $preheader): string
    {
        $span = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">'.e($preheader)
            .str_repeat('&#847;&zwnj;&nbsp;', 30).'</div>';

        return preg_match('/<body[^>]*>/i', $html)
            ? preg_replace('/(<body[^>]*>)/i', '$1'.$span, $html, 1)
            : $span.$html;
    }

    public function rewriteLinks(string $html, callable $rewrite): string
    {
        return preg_replace_callback('/(<a\b[^>]*?\bhref\s*=\s*)(["\'])(.*?)\2/is', function ($m) use ($rewrite) {
            $url = html_entity_decode(trim($m[3]));
            if (! preg_match('#^https?://#i', $url)) {
                return $m[0]; // mailto:, tel:, anchors, unrendered tags
            }

            return $m[1].$m[2].e($rewrite($url)).$m[2];
        }, $html);
    }

    protected function appendUtm(string $url, array $utm): string
    {
        $params = array_filter([
            'utm_source' => $utm['source'] ?? null,
            'utm_medium' => $utm['medium'] ?? null,
            'utm_campaign' => $utm['campaign'] ?? null,
            'utm_term' => $utm['term'] ?? null,
            'utm_content' => $utm['content'] ?? null,
        ]);
        if (! $params) {
            return $url;
        }

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($params).($fragment !== null ? '#'.$fragment : '');
    }

    protected function addHeaders(Email $email, Delivery $delivery, string $unsubscribeUrl): void
    {
        $headers = $email->getHeaders();
        // RFC 8058 one-click unsubscribe (required by Gmail/Yahoo for bulk senders)
        $headers->addTextHeader('List-Unsubscribe', '<'.$unsubscribeUrl.'>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $headers->addTextHeader('Precedence', 'bulk');
        $headers->addTextHeader('X-SM-Delivery', $delivery->uid);
        if ($delivery->campaign) {
            $headers->addTextHeader('Feedback-ID', $delivery->campaign->uid.':'.$delivery->workspace->uid.':sciat');
        }
        $headers->add(new MetadataHeader('delivery', $delivery->uid));
    }

    /**
     * DKIM-sign with the workspace's verified domain key. API providers sign with their
     * own keys for the domain verified in their console, so only SMTP transports are signed.
     */
    protected function sign(Email $email, int $workspaceId, SendingServer $server, bool $enabled): Message
    {
        if (! $enabled || ! $server->usesSmtp()) {
            return $email;
        }

        $from = $email->getFrom()[0]->getAddress();
        $domain = SendingDomain::forEmail($from, $workspaceId);
        if (! $domain || ! $domain->dkim_verified) {
            return $email;
        }

        $signer = new DkimSigner($domain->dkim_private_key, $domain->name, $domain->dkim_selector);

        return $signer->sign($email, ['headers_to_ignore' => ['x-sm-delivery', 'x-metadata-delivery']]);
    }
}
