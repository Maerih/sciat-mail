<x-install-layout title="Finished" step="done">
    <div class="card card-body text-center">
        <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="check" class="size-8" /></span>
        <h2 class="mt-4 text-xl font-bold">Installation complete</h2>
        <p class="mt-1 text-sm text-[var(--muted)]">One last step makes scheduled campaigns, automations and sending work.</p>
    </div>
    <div class="card mt-6" x-data="copy(@js($cron))">
        <div class="card-header"><div><h2 class="card-title">Add the cron job</h2><p class="text-xs text-[var(--muted)]">cPanel → Cron Jobs → Common settings: “Once Per Minute (* * * * *)”, then paste the command.</p></div></div>
        <div class="card-body space-y-3">
            <div class="flex gap-2"><div class="code flex-1">{{ $cron }}</div><button type="button" class="btn-secondary" @click="copy()" x-text="copied ? 'Copied' : 'Copy'"></button></div>
            <p class="help">cPanel shows only the command part (after the five stars). If <code>/usr/local/bin/php</code> is not right for your host, use the PHP path from its documentation, e.g. <code>/usr/local/bin/ea-php83</code>.</p>
        </div>
    </div>
    <div class="card card-body mt-6 space-y-2 text-sm">
        <h2 class="card-title">Then, inside the platform</h2>
        <p>1. <strong>Sending → Sending servers → Add server → SMTP</strong>: enter your own SMTP server's details and click Test.</p>
        <p>2. <strong>Settings → System mail</strong>: the same SMTP account for password resets and notifications.</p>
        <p>3. <strong>Sending → Sending domains</strong>: add your domain and publish the SPF, DKIM and DMARC records it shows.</p>
        <p>4. <strong>Settings → Cron &amp; queue</strong>: confirm the scheduler shows “Running” a minute after adding the cron job.</p>
    </div>
    <div class="mt-6 flex justify-center"><a href="{{ $url }}/login" class="btn-primary">Log in <x-icon name="arrow_forward" class="size-4.5" /></a></div>
</x-install-layout>
