@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-sm">
        <p class="text-gray-500">
            {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}
            @if (method_exists($paginator, 'total')) of {{ number_format($paginator->total()) }} @endif
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn-secondary btn-sm opacity-50">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn-secondary btn-sm">Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn-secondary btn-sm">Next</a>
            @else
                <span class="btn-secondary btn-sm opacity-50">Next</span>
            @endif
        </div>
    </nav>
@endif
