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

    public function findByToken(string $token): ?EmailInbox
    {
        return EmailInbox::query()
            ->where('token', $token)
            ->where('status', '!=', EmailInbox::STATUS_DISABLED)
            ->first();
    }

    public function currentForEmpresa(int $idEmpresa): ?EmailInbox
    {
        return EmailInbox::query()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('status', [EmailInbox::STATUS_ACTIVE, EmailInbox::STATUS_PAUSED])
            ->orderByDesc('id')
            ->first();
    }

    public function storeVerification(EmailInbox $inbox, array $detected): void
    {
        $inbox->update([
            'verification_code' => $detected['code'] ?? null,
            'verification_link' => $detected['link'] ?? null,
            'verification_received_at' => now(),
            'last_email_at' => now(),
        ]);
        $inbox->increment('emails_received');
    }

    public function pause(EmailInbox $inbox): EmailInbox
    {
        $inbox->update(['status' => EmailInbox::STATUS_PAUSED]);
        $this->setAccountActive($inbox, false);

        return $inbox->fresh();
    }

    public function resume(EmailInbox $inbox): EmailInbox
    {
        $inbox->update(['status' => EmailInbox::STATUS_ACTIVE, 'revoked_at' => null]);
        $this->setAccountActive($inbox, true);

        return $inbox->fresh();
    }

    public function disable(EmailInbox $inbox): EmailInbox
    {
        $inbox->update([
            'status' => EmailInbox::STATUS_DISABLED,
            'revoked_at' => now(),
        ]);
        $this->setAccountActive($inbox, false);

        return $inbox->fresh();
    }

    public function regenerate(EmailInbox $inbox, int $userId): EmailInbox
    {
        $this->disable($inbox);

        return $this->createForEmpresa((int) $inbox->id_empresa, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(EmailInbox $inbox): array
    {
        return [
            'id' => $inbox->id,
            'email' => $inbox->email,
            'token' => $inbox->token,
            'status' => $inbox->status,
            'last_email_at' => $inbox->last_email_at?->toIso8601String(),
            'last_dte_at' => $inbox->last_dte_at?->toIso8601String(),
            'emails_received' => (int) $inbox->emails_received,
            'dtes_imported' => (int) $inbox->dtes_imported,
            'verification_code' => $inbox->verification_code,
            'verification_link' => $inbox->verification_link,
            'verification_received_at' => $inbox->verification_received_at?->toIso8601String(),
        ];
    }

    private function setAccountActive(EmailInbox $inbox, bool $active): void
    {
        $account = $inbox->userEmailAccount()->withoutGlobalScopes()->first();
        if ($account) {
            $account->update(['is_active' => $active]);
        }
    }
}
