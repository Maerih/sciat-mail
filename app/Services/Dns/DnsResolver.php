<?php

namespace App\Services\Dns;

interface DnsResolver
{
    /** @return string[] TXT strings (multi-part records joined) */
    public function txt(string $host): array;

    /** @return string[] CNAME targets without trailing dot */
    public function cname(string $host): array;
}
