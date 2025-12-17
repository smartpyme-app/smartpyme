<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestNovaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $message;
    public $tries = 3;
    public $timeout = 60;

    public function __construct($message = 'Test message from prueba-nova route')
    {
        $this->message = $message;
    }

    public function handle()
    {
        $logMessage = 'NOVA Test Job processed: ' . $this->message . ' at ' . now()->toDateTimeString();
        
        Log::info($logMessage);
        
        // Simulate some work
        sleep(2);
        
        Log::info('NOVA Test Job completed successfully');
    }

    public function failed(\Throwable $exception)
    {
        Log::error('NOVA Test Job failed: ' . $exception->getMessage());
    }
}