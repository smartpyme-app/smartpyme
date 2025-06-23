<?php

namespace App\Console\Commands\Authorization;

use App\Services\Authorization\AuthorizationService;
use Illuminate\Console\Command;

class ExpireAuthorizations extends Command
{
    protected $signature = 'authorizations:expire';
    protected $description = 'Expire old pending authorizations';

    protected $authorizationService;

    public function __construct(AuthorizationService $authorizationService)
    {
        parent::__construct();
        $this->authorizationService = $authorizationService;
    }

    public function handle()
    {
        $this->authorizationService->expireOldAuthorizations();
        $this->info('Old authorizations have been expired.');
    }
}