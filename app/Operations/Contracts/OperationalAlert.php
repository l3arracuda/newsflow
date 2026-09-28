<?php

namespace App\Operations\Contracts;

interface OperationalAlert
{
    public function send(string $level, string $message, array $context = []): void;
}
