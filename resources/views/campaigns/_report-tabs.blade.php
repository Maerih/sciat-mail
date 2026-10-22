@php
    $logIcons = ['deliveries' => 'outgoing_mail', 'opens' => 'visibility', 'clicks' => 'ads_click', 'bounces' => 'replay', 'complaints' => 'flag', 'unsubscribes' => 'unsubscribe'];
    $tabs = [
        ['Overview', route('campaigns.show', $campaign), request()->routeIs('campaigns.show'), 'auto_graph'],
        ['Links', route('campaigns.links', $campaign), request()->routeIs('campaigns.links'), 'link'],
        ['Map', route('campaigns.map', $campaign), request()->routeIs('campaigns.map'), 'public'],
        ['Email review', route('campaigns.review', $campaign), request()->routeIs('campaigns.review'), 'preview'],
    ];
    foreach (\App\Http\Controllers\CampaignReportController::LOGS as $key => $label) {
        $tabs[] = [$label, route('campaigns.log', [$campaign, $key]), request()->route('log') === $key, $logIcons[$key] ?? 'article'];
    }
@endphp
<x-pill-tabs :items="$tabs" />
