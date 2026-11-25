<?php

namespace App\Contracts;

use App\Models\DteManagement\UserEmailAccount;
use Carbon\Carbon;
use Illuminate\Support\Enumerable;

interface EmailReaderInterface
{
    /**
     * Get emails with DTE attachments (JSON or XML) in date range.
     *
     * Se recorre una sola vez: las implementaciones devuelven una LazyCollection para no tener
     * todos los adjuntos del rango en memoria a la vez.
     *
     * @return Enumerable<int, array{
     *     email_message_id: string,
     *     clave: ?string,
     *     source_format: string,
     *     source_content: string,
     *     pdf_content: ?string,
     *     acuse_content: ?string
     * }>
     */
    public function getEmailsWithDteAttachments(UserEmailAccount $account, Carbon $from, Carbon $to): Enumerable;
}
