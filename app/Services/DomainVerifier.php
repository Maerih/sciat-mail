<?php

namespace App\Services;

use App\Models\SendingDomain;
use App\Models\TrackingDomain;
use App\Services\Dns\DnsResolver;

class DomainVerifier
{
    public function __construct(protected DnsResolver $dns) {}

    public function verifySending(SendingDomain $domain): SendingDomain
    {
        $norm = fn ($s) => preg_replace('/\s+/', '', strtolower($s));

        $ownership = collect($this->dns->txt($domain->verificationHost()))
            ->contains(fn ($t) => trim($t) === $domain->verificationValue());

        $publicKey = $domain->dkimPublicKeyBody();
        $dkim = collect($this->dns->txt($domain->dkimHost()))
            ->contains(fn ($t) => str_contains(preg_replace('/\s+/', '', $t), 'p='.$publicKey));

        $spf = collect($this->dns->txt($domain->name))
            ->contains(fn ($t) => str_starts_with($norm($t), 'v=spf1'));

        $dmarc = collect($this->dns->txt($domain->dmarcHost()))
            ->contains(fn ($t) => str_starts_with($norm($t), 'v=dmarc1'));

        $domain->forceFill([
            'ownership_verified' => $ownership,
            'dkim_verified' => $dkim,
            'spf_verified' => $spf,
            'dmarc_verified' => $dmarc,
            'checked_at' => now(),
        ])->save();

        return $domain;
    }

    public function verifyTracking(TrackingDomain $domain): TrackingDomain
    {
        $target = strtolower(TrackingDomain::cnameTarget());
        $ok = in_array($target, $this->dns->cname($domain->host), true);
        $domain->forceFill(['verified' => $ok, 'checked_at' => now()])->save();

        return $domain;
    }
}
