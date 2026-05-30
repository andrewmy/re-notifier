<?php

declare(strict_types=1);

namespace App\Application;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

use function http_build_query;
use function str_contains;
use function str_replace;
use function strlen;
use function substr;

final readonly class TelegramNotifier implements Notifier
{
    private const int CAPTION_LIMIT = 1024;

    public function __construct(
        private string $tgUri,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private LoggerInterface $logger,
    ) {
    }

    /** @throws ClientExceptionInterface */
    public function send(string $message, string|null $imageUrl): void
    {
        if ($imageUrl === null) {
            $this->sendMessage($message);

            return;
        }

        $photoUri = $this->photoUri();
        if ($photoUri === null) {
            $this->sendMessage($message);

            return;
        }

        try {
            $this->postForm(
                $photoUri,
                [
                    'photo' => $imageUrl,
                    'caption' => self::caption($message),
                ],
            );
        } catch (ClientExceptionInterface $exception) {
            $this->logger->error('Could not post Telegram photo: ' . $exception->getMessage());
            $this->sendMessage($message);
        }
    }

    /** @throws ClientExceptionInterface */
    private function sendMessage(string $message): void
    {
        $this->postForm($this->tgUri, ['text' => $message]);
    }

    private function photoUri(): string|null
    {
        if (! str_contains($this->tgUri, '/sendMessage?')) {
            $this->logger->warning('Telegram URI does not contain /sendMessage?, sending text-only messages');

            return null;
        }

        return str_replace('/sendMessage?', '/sendPhoto?', $this->tgUri);
    }

    private static function caption(string $message): string
    {
        if (strlen($message) <= self::CAPTION_LIMIT) {
            return $message;
        }

        return substr($message, 0, self::CAPTION_LIMIT - 3) . '...';
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ClientExceptionInterface
     */
    private function postForm(string $uri, array $params): void
    {
        $request = $this->requestFactory
            ->createRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query($params)));

        $this->httpClient->sendRequest($request);
    }
}
