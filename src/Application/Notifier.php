<?php

declare(strict_types=1);

namespace App\Application;

interface Notifier
{
    public function send(string $message, string|null $imageUrl): void;
}
