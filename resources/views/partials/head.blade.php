{{-- Shared <head> assets: fonts, icons, theme bootstrap (runs before paint to avoid a flash) --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0..1,0&display=block" rel="stylesheet">
<script>
    (function () {
        var saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) {}
        document.documentElement.dataset.theme = saved || @js(\App\Models\Setting::get('default_theme', 'dark'));
    })();
</script>
@if ($favicon = \App\Models\Setting::get('platform_favicon'))
    <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($favicon) }}">
@endif
