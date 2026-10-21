<?php

namespace App\Services\Dns;

class SystemDnsResolver implements DnsResolver
{
    public function txt(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT) ?: [];

        return array_map(fn ($r) => isset($r['entries']) ? implode('', $r['entries']) : ($r['txt'] ?? ''), $records);
    }

    public function mx(string $domain): bool
    {
        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }

    public function cname(string $host): array
    {
        $records = @dns_get_record($host, DNS_CNAME) ?: [];

        return array_map(fn ($r) => rtrim(strtolower($r['target'] ?? ''), '.'), $records);
    }
}
