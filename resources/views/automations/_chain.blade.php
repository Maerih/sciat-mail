{{-- Renders a chain of steps starting at $stepId; conditions split into yes/no columns --}}
@php $step = $flow->get($stepId); @endphp
@if ($step)
    @php
        $email = $step['type'] === 'send' ? $emails->get($step['campaign_id'] ?? 0) : null;
        $waiting = (int) ($onStep[$stepId] ?? 0);
        [$icon, $tone, $title, $detail] = match ($step['type']) {
            'send' => ['envelope', 'bg-brand-50 text-brand-600', 'Send email', $email?->subject ?: 'No subject yet'],
            'wait' => ['clock', 'bg-amber-50 text-amber-600', 'Wait', ($step['amount'] ?? 1).' '.($step['unit'] ?? 'days')],
            'condition' => ['branch', 'bg-violet-50 text-violet-600', 'Condition', \App\Services\Automation\Flow::CONDITIONS[$step['rule']['kind'] ?? ''] ?? 'Condition'],
            'action' => ['tag', 'bg-emerald-50 text-emerald-600', \App\Services\Automation\Flow::ACTIONS[$step['action'] ?? ''] ?? 'Action', $step['tag'] ?? $step['field'] ?? ''],
        };
        if ($step['type'] === 'condition') {
            $rule = $step['rule'] ?? [];
            $detail = match ($rule['kind'] ?? '') {
                'opened', 'clicked' => ($rule['kind'] === 'opened' ? 'Opened ' : 'Clicked in ').(! empty($rule['step'])
                    ? '“'.($emails->get($flow->get($rule['step'])['campaign_id'] ?? 0)?->subject ?? 'email').'”' : 'any email'),
                'tag' => 'Has tag “'.($rule['tag'] ?? '').'”',
                'field' => ($rule['field'] ?? '').' '.(\App\Models\Segment::OPERATORS['text'][$rule['operator'] ?? 'equal'] ?? '').' '.($rule['value'] ?? ''),
                default => $detail,
            };
        }
    @endphp
    <div class="w-72 shrink-0">
        <div class="card group relative p-3.5">
            <div class="flex items-start gap-3">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $tone }}"><x-icon :name="$icon" class="size-4.5" /></span>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-900">{{ $title }}</div>
                    <div class="truncate text-xs text-gray-500" title="{{ $detail }}">{{ $detail }}</div>
                    @if ($email && ($s = $emailStats[$email->id] ?? null) && $s['sent'] > 0)
                        <div class="mt-1.5 flex gap-3 text-xs text-gray-600">
                            <span>{{ number_format($s['sent']) }} sent</span><span>{{ $s['open_rate'] }}% open</span><span>{{ $s['click_rate'] }}% click</span>
                        </div>
                    @endif
                    @if ($waiting)
                        <div class="mt-1.5"><span class="badge-yellow">{{ number_format($waiting) }} here now</span></div>
                    @endif
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-1 border-t border-gray-100 pt-2.5">
                <a href="{{ $step['type'] === 'send' ? route('automations.email', [$automation, $stepId]) : route('automations.step', [$automation, $stepId]) }}" class="btn-ghost btn-sm">Edit</a>
                @if ($email && ($emailStats[$email->id]['total'] ?? 0) > 0)
                    <a href="{{ route('campaigns.show', $email) }}" class="btn-ghost btn-sm">Report</a>
                @endif
                <form method="POST" action="{{ route('automations.step.destroy', [$automation, $stepId]) }}" class="ml-auto"
                      onsubmit="return confirm(@js($step['type'] === 'condition' ? 'Remove this condition? Steps on its NO path are deleted; the YES path is kept.' : 'Remove this step?'))">
                    @csrf @method('DELETE')
                    <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50"><x-icon name="trash" class="size-4" /></button>
                </form>
            </div>
        </div>
    </div>

    @if ($step['type'] === 'condition')
        <div class="h-5 w-px bg-gray-300"></div>
        <div class="flex items-start gap-6 border-t border-gray-300 pt-0">
            @foreach (['yes' => ['Yes', 'text-emerald-700 bg-emerald-50'], 'no' => ['No', 'text-red-700 bg-red-50']] as $branch => [$label, $badge])
                <div class="flex flex-col items-center">
                    <div class="h-4 w-px bg-gray-300"></div>
                    <span class="badge {{ $badge }}">{{ $label }}</span>
                    @include('automations._add', ['parent' => $stepId, 'branch' => $branch])
                    @if ($step[$branch] ?? null)
                        @include('automations._chain', ['stepId' => $step[$branch]])
                    @else
                        <div class="text-xs text-gray-400">End</div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        @include('automations._add', ['parent' => $stepId, 'branch' => 'next'])
        @if ($step['next'] ?? null)
            @include('automations._chain', ['stepId' => $step['next']])
        @else
            <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">End</div>
        @endif
    @endif
@endif
