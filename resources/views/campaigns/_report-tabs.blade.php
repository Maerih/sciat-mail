<div class="mb-6 flex gap-6 overflow-x-auto border-b border-gray-200">
    <a href="{{ route('campaigns.show', $campaign) }}" @class(['tab', 'active' => request()->routeIs('campaigns.show')])>Overview</a>
    @foreach (\App\Http\Controllers\CampaignReportController::LOGS as $key => $label)
        <a href="{{ route('campaigns.log', [$campaign, $key]) }}" @class(['tab', 'active' => request()->route('log') === $key])>{{ $label }}</a>
    @endforeach
</div>
