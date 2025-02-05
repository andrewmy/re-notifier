<?php

declare(strict_types=1);

namespace App\Domain;

interface AdRepository
{
    public function exists(string $url, string $description): bool;

    public function save(Ad $ad): void;
}
