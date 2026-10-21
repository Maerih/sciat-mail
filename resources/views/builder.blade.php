<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Builder · {{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <script>
        window.builderConfig = {!! json_encode(['html' => $html, 'project' => $project, 'saveUrl' => $saveUrl, 'backUrl' => $backUrl,
            'uploadUrl' => $uploadUrl, 'assets' => $assets, 'csrf' => csrf_token()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) !!};
    </script>
    @vite(['resources/js/builder.js'])
</head>
<body class="flex h-full flex-col bg-gray-900 font-sans antialiased">
    <header class="flex h-14 shrink-0 items-center gap-3 border-b border-white/10 px-4 text-white">
        <a href="{{ $backUrl }}" class="rounded-lg px-2 py-1 text-sm text-gray-300 hover:bg-white/10" onclick="return !document.getElementById('builder-status').textContent.startsWith('Unsaved') || confirm('Leave without saving?')">&larr; Back</a>
        <div class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $title }}</div>
        <span id="builder-status" class="hidden text-xs text-gray-400 sm:inline">All changes saved</span>
        <select id="builder-tag" class="rounded-lg border-0 bg-white/10 py-1.5 text-sm text-white">
            <option value="">Merge tags…</option>
            @foreach ($tags as $tag => $label)<option value="{{ $tag }}" class="text-gray-900">{{ $label }}</option>@endforeach
        </select>
        <button id="builder-save" class="btn-secondary btn-sm">Save</button>
        <button id="builder-done" class="btn-primary btn-sm">Save & close</button>
    </header>
    <div class="min-h-0 flex-1"><div id="gjs"></div></div>
</body>
</html>
