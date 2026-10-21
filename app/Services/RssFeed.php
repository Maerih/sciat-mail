<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches RSS 2.0 / Atom feeds and renders them for emails. Results are cached briefly so
 * a large send hits the feed once, not once per recipient.
 */
class RssFeed
{
    public const CACHE_SECONDS = 900;

    /** @return array<int, array{id: string, title: string, link: string, summary: string, date: ?string, image: ?string}> */
    public function items(string $url, int $limit = 5, bool $fresh = false): array
    {
        $key = 'rss:'.md5($url);
        if ($fresh) {
            Cache::forget($key);
        }

        $items = Cache::remember($key, self::CACHE_SECONDS, fn () => $this->fetch($url));

        return array_slice($items, 0, max(1, $limit));
    }

    /** @return array<int, array<string, ?string>> */
    protected function fetch(string $url): array
    {
        try {
            $response = Http::timeout(15)->withHeaders(['User-Agent' => config('app.name').' RSS'])->get($url);
        } catch (\Throwable) {
            return [];
        }
        if ($response->failed()) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            return [];
        }

        $items = [];
        if (isset($xml->channel->item)) { // RSS 2.0
            foreach ($xml->channel->item as $item) {
                $media = $item->children('http://search.yahoo.com/mrss/');
                $items[] = $this->item(
                    (string) ($item->guid ?: $item->link),
                    (string) $item->title,
                    (string) $item->link,
                    (string) ($item->description ?? ''),
                    (string) ($item->pubDate ?? ''),
                    isset($item->enclosure) && str_starts_with((string) $item->enclosure['type'], 'image') ? (string) $item->enclosure['url']
                        : (isset($media->content) ? (string) $media->content->attributes()->url : null),
                );
            }
        } elseif (isset($xml->entry)) { // Atom
            foreach ($xml->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    if ((string) ($l['rel'] ?? 'alternate') === 'alternate') {
                        $link = (string) $l['href'];
                    }
                }
                $items[] = $this->item((string) ($entry->id ?: $link), (string) $entry->title, $link,
                    (string) ($entry->summary ?: $entry->content), (string) ($entry->published ?: $entry->updated), null);
            }
        }

        return $items;
    }

    protected function item(string $id, string $title, string $link, string $summary, string $date, ?string $image): array
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5)));

        return [
            'id' => $id,
            'title' => trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5)),
            'link' => preg_match('#^https?://#i', $link) ? $link : '',
            'summary' => mb_strlen($text) > 220 ? mb_substr($text, 0, 217).'…' : $text,
            'date' => $date !== '' && strtotime($date) ? date('M j, Y', strtotime($date)) : null,
            'image' => $image && preg_match('#^https?://#i', $image) ? $image : null,
        ];
    }

    /** Email-safe HTML block listing the items. */
    public function render(array $items): string
    {
        return view('emails.partials.rss-items', ['items' => $items])->render();
    }

    /** @return array<string, string> RSS merge tag values */
    public function mergeValues(?array $config): array
    {
        if (empty($config['url'])) {
            return [];
        }
        $items = $this->items($config['url'], (int) ($config['count'] ?? 5));

        return [
            'RSS_ITEMS' => $items ? $this->render($items) : '',
            'RSS_LATEST_TITLE' => $items[0]['title'] ?? '',
            'RSS_LATEST_URL' => $items[0]['link'] ?? '',
        ];
    }
}
