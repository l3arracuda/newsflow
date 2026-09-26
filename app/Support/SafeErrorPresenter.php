<?php

namespace App\Support;

class SafeErrorPresenter
{
    public function sanitize(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }
        $message = preg_replace('/(authorization\s*[:=]\s*bearer\s+)[^\s,;]+/i', '$1[REDACTED]', $message) ?? '';
        $message = preg_replace('/\b(token|password|secret|api[_-]?key)\s*[:=]\s*[^&\s,;]+/i', '$1=[REDACTED]', $message) ?? '';

        return preg_replace('/([?&](?:token|password|secret|api[_-]?key)=)[^&\s]+/i', '$1[REDACTED]', $message) ?? '';
    }
}
