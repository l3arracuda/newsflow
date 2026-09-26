<?php

namespace App\AI\Contracts;

interface FactConsistencyChecker
{
    public function check(array $facts, array $draft): array;
}
