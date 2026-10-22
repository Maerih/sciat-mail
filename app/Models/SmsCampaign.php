<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsCampaign extends Model
{
    use BelongsToWorkspace, HasUid;

    public const AUDIENCES = [
        'list' => 'Everyone on a list',
        'segment' => 'A segment of a list',
        'non_openers' => 'People who did not open an email campaign',
    ];

    protected $fillable = ['name', 'sms_provider_id', 'sender_id', 'message', 'opt_out_text', 'audience', 'scheduled_at'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SmsProvider::class, 'sms_provider_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    public function audienceValue(string $key, $default = null)
    {
        return ($this->audience ?? [])[$key] ?? $default;
    }

    public function fullMessage(): string
    {
        return trim((string) $this->message.(filled($this->opt_out_text) ? "\n".$this->opt_out_text : ''));
    }

    /** Subscribed contacts with a phone number who haven't opted out of SMS. */
    public function recipientQuery(): Builder
    {
        $list = MailList::where('workspace_id', $this->workspace_id)->find($this->audienceValue('list_id'));
        $query = Subscriber::query()->whereRaw('1 = 0');

        if ($this->audienceValue('type') === 'non_openers') {
            $campaign = Campaign::where('workspace_id', $this->workspace_id)->find($this->audienceValue('campaign_id'));
            if ($campaign) {
                $query = Subscriber::whereIn('id', $campaign->deliveries()->whereNotNull('sent_at')->whereNull('opened_at')->select('subscriber_id'));
            }
        } elseif ($list) {
            $query = $list->subscribers()->getQuery();
            if ($this->audienceValue('type') === 'segment' && ($segment = $list->segments()->find($this->audienceValue('segment_id')))) {
                $query = $segment->subscribersQuery();
            }
        }

        return $query->where('status', 'subscribed')->whereNotNull('phone')->whereNull('sms_opted_out_at');
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $counts = $this->messages()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $total = (int) $counts->sum();
        $delivered = (int) ($counts['delivered'] ?? 0);
        $sent = $delivered + (int) ($counts['sent'] ?? 0);

        return [
            'total' => $total,
            'queued' => (int) ($counts['queued'] ?? 0),
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => (int) ($counts['failed'] ?? 0),
            'parts' => (int) $this->messages()->sum('parts'),
            'delivery_rate' => $sent ? round($delivered / $sent * 100, 1) : 0,
            'progress' => $total ? (int) round(($total - (int) ($counts['queued'] ?? 0)) / $total * 100) : 0,
        ];
    }
}
