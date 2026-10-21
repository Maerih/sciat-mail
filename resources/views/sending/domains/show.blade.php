<x-layouts.app :title="$domain->name">
    <x-page-header :title="$domain->name" :subtitle="$domain->isVerified() ? 'Verified — campaigns from this domain are DKIM-signed.' : 'Publish these DNS records at the domain\'s DNS provider, then verify.'" :back="route('sending.domains.index')">
        <x-slot:actions>
            <form method="POST" action="{{ route('sending.domains.verify', $domain) }}">@csrf<button class="btn-primary">Verify DNS records</button></form>
            <x-confirm-delete :action="route('sending.domains.destroy', $domain)" label="Remove" :message="'Remove '.$domain->name.'?'" class="btn-ghost text-red-600 hover:bg-red-50" />
        </x-slot:actions>
    </x-page-header>

    @php
        $spf = 'v=spf1 '.($spfIncludes->isEmpty() ? '' : $spfIncludes->map(fn ($i) => 'include:'.$i)->implode(' ').' ').'~all';
        $records = [
            ['Ownership', 'TXT', $domain->verificationHost(), $domain->verificationValue(), $domain->ownership_verified, 'Proves this client controls the domain.'],
            ['DKIM', 'TXT', $domain->dkimHost(), $domain->dkimValue(), $domain->dkim_verified, 'Public key used to verify the signature on SMTP-sent mail.'],
            ['SPF', 'TXT', $domain->name, $spf, $domain->spf_verified, 'Authorises your sending servers. If an SPF record exists, merge the include: parts into it — a domain may have only one SPF record. Add your own SMTP server IPs as ip4: entries.'],
            ['DMARC', 'TXT', $domain->dmarcHost(), 'v=DMARC1; p=none; rua=mailto:dmarc@'.$domain->name, $domain->dmarc_verified, 'Required by Gmail and Yahoo for bulk senders. Start with p=none and tighten later.'],
        ];
    @endphp

    <div class="space-y-4">
        @foreach ($records as [$label, $type, $host, $value, $ok, $help])
            <div class="card card-body">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="card-title">{{ $label }} <span class="ml-1 badge-gray">{{ $type }}</span></h2>
                    @if ($ok)<span class="badge-green"><x-icon name="check" class="size-3.5" /> Found</span>@else<span class="badge-yellow">Not found yet</span>@endif
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ $help }}</p>
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <div x-data="copy(@js($host))"><div class="mb-1 flex justify-between text-xs font-medium text-gray-500">Host / name <button type="button" @click="copy()" class="text-brand-600" x-text="copied ? 'Copied' : 'Copy'"></button></div><div class="code">{{ $host }}</div></div>
                    <div class="md:col-span-2" x-data="copy(@js($value))"><div class="mb-1 flex justify-between text-xs font-medium text-gray-500">Value <button type="button" @click="copy()" class="text-brand-600" x-text="copied ? 'Copied' : 'Copy'"></button></div><div class="code">{{ $value }}</div></div>
                </div>
            </div>
        @endforeach
        <p class="text-sm text-gray-500">Using Amazon SES, SendGrid or Mailgun? Also verify the domain inside that provider's console — they sign with their own DKIM keys. Some DNS hosts append the domain automatically: enter only the part before <code>.{{ $domain->name }}</code> as the host if so.</p>
    </div>
</x-layouts.app>
