<?php

namespace App\Jobs;

use App\Models\Blacklist;
use App\Models\Import;
use App\Models\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;

/**
 * Imports a mapped CSV into a list in chunks. Existing subscribers get their field
 * values merged (status untouched); suppressed addresses are imported as blacklisted.
 */
class ProcessImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public const CHUNK = 500;

    public const MAX_ERRORS = 200;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $import = Import::with('list.fields')->find($this->importId);
        if (! $import || $import->status !== 'queued') {
            return;
        }

        $import->forceFill(['status' => 'running'])->save();
        $list = $import->list;
        $mapping = $import->mapping; // [column index => tag | 'TAGS' | '']
        $emailColumn = array_search('EMAIL', $mapping, true);
        $validTags = $list->fields->pluck('tag')->all();
        $errors = [];

        $csv = Reader::createFromPath(Storage::path($import->path));
        $csv->setDelimiter(static::detectDelimiter(Storage::path($import->path)));
        $records = $csv->getRecords();

        $chunk = [];
        $line = 0;
        foreach ($records as $row) {
            $line++;
            if ($line === 1) {
                continue; // header row
            }
            $email = strtolower(trim((string) ($row[$emailColumn] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $import->failed_count++;
                if (count($errors) < self::MAX_ERRORS) {
                    $errors[] = "Line {$line}: invalid email \"".Str::limit($email, 60).'"';
                }
                $import->processed++;
                continue;
            }

            $data = [];
            $tags = [];
            foreach ($mapping as $index => $tag) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($tag === 'TAGS' && $value !== '') {
                    $tags = array_merge($tags, array_filter(array_map('trim', preg_split('/[,;|]/', $value))));
                } elseif (in_array($tag, $validTags, true) && $value !== '') {
                    $data[$tag] = $value;
                }
            }
            $chunk[$email] = ['data' => $data, 'tags' => $tags]; // later rows win on duplicates

            if (count($chunk) >= self::CHUNK) {
                $this->flush($import, $chunk);
                $chunk = [];
            }
        }

        if ($chunk) {
            $this->flush($import, $chunk);
        }

        $import->forceFill(['status' => 'done', 'errors' => $errors, 'finished_at' => now()])->save();
        Storage::delete($import->path);
    }

    protected function flush(Import $import, array $rows): void
    {
        $list = $import->list;
        $emails = array_keys($rows);

        DB::transaction(function () use ($import, $list, $rows, $emails) {
            $existing = Subscriber::where('mail_list_id', $list->id)->whereIn('email', $emails)->get()->keyBy('email');
            $blocked = Blacklist::whereIn('email', $emails)
                ->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $list->workspace_id))
                ->pluck('email')->flip();

            $inserts = [];
            $now = now();
            foreach ($rows as $email => $row) {
                if ($subscriber = $existing->get($email)) {
                    $subscriber->data = array_merge($subscriber->data ?? [], $row['data']);
                    if ($row['tags']) {
                        $subscriber->addTags($row['tags']);
                    }
                    if ($subscriber->isDirty()) {
                        $subscriber->save();
                    }
                    $import->updated_count++;
                } else {
                    $inserts[] = [
                        'uid' => strtolower(Str::random(13)),
                        'mail_list_id' => $list->id,
                        'email' => $email,
                        'status' => isset($blocked[$email]) ? 'blacklisted' : 'subscribed',
                        'data' => json_encode((object) $row['data']),
                        'tags' => $row['tags'] ? json_encode(array_values(array_unique($row['tags']))) : null,
                        'source' => 'import',
                        'confirmed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $import->created_count++;
                }
            }

            if ($inserts) {
                DB::table('subscribers')->insertOrIgnore($inserts);
            }
        });

        $import->processed += count($rows);
        $import->save();
    }

    public static function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = (string) fgets($handle);
        fclose($handle);

        $counts = [];
        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $counts[$delimiter] = substr_count($line, $delimiter);
        }
        arsort($counts);

        return reset($counts) > 0 ? (string) array_key_first($counts) : ',';
    }

    /** Counts data rows for progress display without loading the file. */
    public static function countRows(string $path): int
    {
        $count = 0;
        $handle = fopen($path, 'r');
        while (fgets($handle) !== false) {
            $count++;
        }
        fclose($handle);

        return max(0, $count - 1);
    }
}
