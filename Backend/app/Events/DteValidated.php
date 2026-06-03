<?php

namespace App\Events;

use App\Models\DteManagement\DteDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DteValidated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DteDocument $document
    ) {
    }
}
