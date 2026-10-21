<x-layouts.app :title="'Review · '.$campaign->name">
    @include('campaigns.wizard._layout')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Pre-flight checklist</h2></div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($checks as $check)
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <span @class(['mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full',
                                'bg-emerald-100 text-emerald-700' => $check['level'] === 'ok',
                                'bg-amber-100 text-amber-700' => $check['level'] === 'warning',
                                'bg-red-100 text-red-700' => $check['level'] === 'error'])>
                                <x-icon :name="$check['level'] === 'ok' ? 'check' : ($check['level'] === 'warning' ? 'warning' : 'x')" class="size-3.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-gray-900">{{ $check['label'] }}</div>
                                <div class="text-sm text-gray-600">{{ $check['message'] }}</div>
                            </div>
                            @if ($check['step'])<a href="{{ route('campaigns.'.$check['step'], $campaign) }}" class="text-sm text-brand-600 hover:underline">Edit</a>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Inbox preview</h2></div>
                <div class="border-b border-gray-100 px-5 py-3 text-sm">
                    <div class="font-semibold text-gray-900">{{ $campaign->from_name }}</div>
                    <div class="text-gray-900">{{ $campaign->subject }}</div>
                    <div class="truncate text-gray-500">{{ $campaign->preheader }}</div>
                </div>
                <iframe src="{{ route('campaigns.preview', $campaign) }}" class="h-[520px] w-full bg-white" sandbox=""></iframe>
            </div>
        </div>

        <div class="space-y-6">
            <form method="POST" action="{{ route('campaigns.launch', $campaign) }}" class="card card-body space-y-3"
                  onsubmit="return confirm('Launch this campaign now? Emails will start going out to real recipients.')">
                @csrf
                <h2 class="card-title">{{ $campaign->scheduled_at?->isFuture() ? 'Schedule campaign' : 'Launch campaign' }}</h2>
                <p class="text-sm text-gray-600">
                    @if ($campaign->scheduled_at?->isFuture())
                        Goes out on {{ $campaign->scheduled_at->setTimezone($campaign->workspace->timezone)->format('D j M Y, H:i') }} ({{ $campaign->workspace->timezone }}).
                    @else
                        Sending starts immediately after you launch.
                    @endif
                </p>
                <button class="btn-primary w-full" @disabled(! $canLaunch)><x-icon name="paper-airplane" /> {{ $campaign->scheduled_at?->isFuture() ? 'Schedule' : 'Launch now' }}</button>
                @unless ($canLaunch)<p class="text-xs text-red-600">Resolve the items marked in red first.</p>@endunless
            </form>
            <form method="POST" action="{{ route('campaigns.test', $campaign) }}" class="card card-body space-y-3">
                @csrf
                <h2 class="card-title">Send a test email</h2>
                <x-field name="emails" label="Recipients" :value="auth()->user()->email" help="Up to 10 addresses, comma separated. Merge tags use the first matching subscriber." />
                <button class="btn-secondary w-full">Send test</button>
            </form>
            @if ($campaign->status === 'scheduled')
                <form method="POST" action="{{ route('campaigns.pause', $campaign) }}" class="card card-body">@csrf
                    <p class="text-sm text-gray-600">This campaign is scheduled. Unschedule it to keep it as a paused draft.</p>
                    <button class="btn-secondary mt-3 w-full">Unschedule</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
