<?php

namespace App\Services\MailIngest;

/**
 * Picks the ingest token from pipe candidates. Never uses header.To.
 */
class IngestRecipientResolver
{
    public const TENANT_DOMAIN = 'ingest.smartpyme.site';

    private const PRIMARY_SOURCES = [
        'argv',
        'env.RECIPIENT',
        'env.LOCAL_PART+DOMAIN',
        'header.Delivered-To',
        'header.X-Original-To',
        'header.Envelope-To',
        'header.X-Envelope-To',
    ];

    public function __construct(
        private readonly string $domain = self::TENANT_DOMAIN
    ) {
    }

    public function tenantDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param  list<array{source: string, value: string}>  $candidates
     */
    public function resolveToken(array $candidates): ?string
    {
        foreach (self::PRIMARY_SOURCES as $source) {
            foreach ($candidates as $candidate) {
                if (($candidate['source'] ?? '') !== $source) {
                    continue;
                }
                $token = $this->tokenIfTenant((string) ($candidate['value'] ?? ''));
                if ($token !== null) {
                    return $token;
                }
            }
        }

        return null;
    }

    public function tokenIfTenant(string $email): ?string
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }
        $local = substr($email, 0, $at);
        $host = substr($email, $at + 1);
        if ($local === '' || $host !== $this->domain) {
            return null;
        }

        return $local;
    }
}
