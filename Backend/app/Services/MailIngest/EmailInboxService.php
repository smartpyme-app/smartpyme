<?php

namespace App\Services\MailIngest;

use App\Models\DteManagement\EmailInbox;
use App\Models\DteManagement\UserEmailAccount;

class EmailInboxService
{
    public function createForEmpresa(int $idEmpresa, int $userId, ?string $token = null): EmailInbox
    {
        $existing = EmailInbox::query()
            ->where('id_empresa', $idEmpresa)
            ->where('purpose', 'dte')
            ->where('status', EmailInbox::STATUS_ACTIVE)
            ->first();
        if ($existing) {
            return $existing;
        }

        $token = $token !== null && $token !== ''
            ? strtolower(preg_replace('/[^a-z0-9]/', '', $token) ?? '')
            : bin2hex(random_bytes(16));
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
        }
        $email = $token . '@' . IngestRecipientResolver::TENANT_DOMAIN;

        $account = UserEmailAccount::withoutGlobalScopes()->create([
            'id_empresa' => $idEmpresa,
            'user_id' => $userId,
            'provider' => 'forward',
            'email' => $email,
            'is_active' => true,
        ]);

        return EmailInbox::query()->create([
            'id_empresa' => $idEmpresa,
            'user_email_account_id' => $account->id,
            'token' => $token,
            'email' => $email,
            'status' => EmailInbox::STATUS_ACTIVE,
            'purpose' => 'dte',
            'created_by_user_id' => $userId,
        ]);
    }

    public function findActiveByToken(string $token): ?EmailInbox
    {
        return EmailInbox::query()
            ->where('token', $token)
            ->where('status', EmailInbox::STATUS_ACTIVE)
            ->first();
    }
}
