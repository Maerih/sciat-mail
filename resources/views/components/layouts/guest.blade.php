@props(['title' => null, 'brand' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? ($brand?->company ?: $brand?->name ?? \App\Services\Branding::appName()) }}</title>
    @include('partials.head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php $color = $brand ? \App\Services\Branding::clientColor($brand) : \App\Services\Branding::appColor(); @endphp
    @if ($brandCss = \App\Services\Branding::css($color))<style>{!! $brandCss !!}</style>@endif
</head>
<body class="flex min-h-full items-center justify-center px-4 py-12 font-sans text-[14px] antialiased">
    <div class="w-full max-w-md">
        @if ($brand)
            <div class="mb-6 flex items-center justify-center gap-3">
                @if ($logo = \App\Services\Branding::clientLogo($brand))
                    <img src="{{ $logo }}" alt="{{ $brand->company ?: $brand->name }}" class="h-12 max-w-[14rem] object-contain">
                @else
                    <span class="text-lg font-semibold text-gray-800">{{ $brand->company ?: $brand->name }}</span>
                @endif
            </div>
        @endif
        {{ $slot }}
        @if ($brand?->website)
            <p class="mt-6 text-center text-xs text-gray-400"><a href="{{ $brand->website }}" class="hover:text-gray-600">{{ parse_url($brand->website, PHP_URL_HOST) }}</a></p>
        @endif
    </div>
</body>
</html>
