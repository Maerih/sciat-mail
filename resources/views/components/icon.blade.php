@props(['name', 'class' => 'size-5', 'fill' => false])
@php
    // App icon names → Material Symbols Rounded ligatures
    $map = [
        'home' => 'home', 'megaphone' => 'mail', 'template' => 'dashboard_customize', 'users' => 'group', 'form' => 'edit_note',
        'server' => 'dns', 'globe' => 'language', 'link' => 'link', 'ban' => 'block', 'cog' => 'settings', 'team' => 'groups',
        'building' => 'apartment', 'plus' => 'add', 'chevron-down' => 'expand_more', 'check' => 'check', 'x' => 'close',
        'warning' => 'warning', 'logout' => 'logout', 'upload' => 'upload_file', 'download' => 'download', 'search' => 'search',
        'trash' => 'delete', 'eye' => 'visibility', 'filter' => 'filter_list', 'paper-airplane' => 'send', 'bolt' => 'bolt',
        'clock' => 'schedule', 'branch' => 'call_split', 'envelope' => 'mail', 'tag' => 'sell',
    ];
    $glyph = $map[$name] ?? $name;
    // Tailwind size-N → font size N*4px so icons keep their layout size
    $px = preg_match('/size-([\d.]+)/', $class, $m) ? (float) $m[1] * 4 : 20;
@endphp
<span {{ $attributes->merge(['class' => 'material-symbols-rounded shrink-0 '.preg_replace('/size-[\d.]+/', '', $class)]) }} style="font-size: {{ $px }}px;{{ $fill ? "font-variation-settings:'FILL' 1" : '' }}" aria-hidden="true">{{ $glyph }}</span>
