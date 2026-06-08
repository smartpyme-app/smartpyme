<?php

namespace App\Contracts;

use App\Models\DteManagement\UserEmailAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface EmailReaderInterface
{
    /**
     * Get emails with DTE attachments (JSON files) in date range.
     *
     * @param UserEmailAccount $account
     * @param Carbon $from
     * @param Carbon $to
     * @return Collection<int, array{email_message_id: string, json_content: string, pdf_content: ?string}>
     */
    public function getEmailsWithDteAttachments(UserEmailAccount $account, Carbon $from, Carbon $to): Collection;
}
