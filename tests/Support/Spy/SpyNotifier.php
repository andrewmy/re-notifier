<?php

declare(strict_types=1);

namespace App\Tests\Support\Spy;

use App\Application\Notifier;

final class SpyNotifier implements Notifier
{
    /** @var list<string> */
    public array $messages = [];

    public function send(string $message, string|null $imageUrl): void
    {
        $this->messages[] = $message;
    }
}
