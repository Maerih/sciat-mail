<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SendingDomain extends Model
{
    use HasUid, BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'name'];

    protected $hidden = ['dkim_private_key'];

    protected function casts(): array
    {
        return [
            'dkim_private_key' => 'encrypted',
            'ownership_verified' => 'boolean',
            'dkim_verified' => 'boolean',
            'spf_verified' => 'boolean',
            'dmarc_verified' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SendingDomain $domain) {
            $domain->name = strtolower(trim($domain->name));
            $domain->verification_token ??= Str::random(32);
            $domain->dkim_selector ??= 'sm'.now()->format('Ym');

            if (empty($domain->dkim_private_key)) {
                $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
                openssl_pkey_export($key, $private);
                $domain->dkim_private_key = $private;
                $domain->dkim_public_key = openssl_pkey_get_details($key)['key'];
            }
        });
    }

    public function isVerified(): bool
    {
        return $this->ownership_verified && $this->dkim_verified;
    }

    public function verificationHost(): string
    {
        return '_sciat-verify.'.$this->name;
    }

    public function verificationValue(): string
    {
        return 'sciat-verification='.$this->verification_token;
    }

    public function dkimHost(): string
    {
        return $this->dkim_selector.'._domainkey.'.$this->name;
    }

    public function dkimPublicKeyBody(): string
    {
        return preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $this->dkim_public_key);
    }

    public function dkimValue(): string
    {
        return 'v=DKIM1; k=rsa; p='.$this->dkimPublicKeyBody();
    }

    public function dmarcHost(): string
    {
        return '_dmarc.'.$this->name;
    }

    public static function forEmail(string $email, int $workspaceId): ?self
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        return $domain ? static::where('workspace_id', $workspaceId)->where('name', $domain)->first() : null;
    }
}
