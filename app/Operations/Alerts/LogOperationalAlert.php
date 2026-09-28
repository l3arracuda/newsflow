<?php

namespace App\Operations\Alerts;

use App\Operations\Contracts\OperationalAlert;
use Illuminate\Support\Facades\Log;

class LogOperationalAlert implements OperationalAlert
{
    public function send(string $level, string $message, array $context = []): void
    {
        Log::log($level, $message, $context);
    }
}
