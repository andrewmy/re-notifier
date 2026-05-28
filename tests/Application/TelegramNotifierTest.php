<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\TelegramNotifier;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

final class TelegramNotifierTest extends TestCase
{
    public function testPhotoMessageUsesSendPhotoWithCaption(): void
    {
        $requests = [];
        $notifier = self::notifier([self::recordResponse($requests)]);

        $notifier->send(
            "https://www.ss.com/msg/example.html\nBrivibas\nK: 4 | m2: 90 | EUR: 250 000",
            'https://i.ss.com/gallery/example.t.jpg',
        );

        self::assertCount(1, $requests);
        self::assertSame(
            'https://api.telegram.org/botTOKEN/sendPhoto?chat_id=123',
            (string) $requests[0]->getUri(),
        );
        self::assertSame(
            'photo=https%3A%2F%2Fi.ss.com%2Fgallery%2Fexample.t.jpg&caption=https%3A%2F%2Fwww.ss.com%2Fmsg%2Fexample.html%0ABrivibas%0AK%3A+4+%7C+m2%3A+90+%7C+EUR%3A+250+000',
            (string) $requests[0]->getBody(),
        );
    }

    public function testPhotoFailureFallsBackToTextMessage(): void
    {
        $requests = [];
        $notifier = self::notifier([self::recordFailure($requests), self::recordResponse($requests)]);

        $notifier->send('https://www.ss.com/msg/example.html', 'https://i.ss.com/gallery/example.t.jpg');

        self::assertCount(2, $requests);
        self::assertSame(
            'https://api.telegram.org/botTOKEN/sendPhoto?chat_id=123',
            (string) $requests[0]->getUri(),
        );
        self::assertSame(
            'https://api.telegram.org/botTOKEN/sendMessage?chat_id=123',
            (string) $requests[1]->getUri(),
        );
        self::assertSame(
            'text=https%3A%2F%2Fwww.ss.com%2Fmsg%2Fexample.html',
            (string) $requests[1]->getBody(),
        );
    }

    public function testMissingImageSendsTextMessage(): void
    {
        $requests = [];
        $notifier = self::notifier([self::recordResponse($requests)]);

        $notifier->send('https://www.ss.com/msg/example.html', null);

        self::assertCount(1, $requests);
        self::assertSame(
            'https://api.telegram.org/botTOKEN/sendMessage?chat_id=123',
            (string) $requests[0]->getUri(),
        );
        self::assertSame(
            'text=https%3A%2F%2Fwww.ss.com%2Fmsg%2Fexample.html',
            (string) $requests[0]->getBody(),
        );
    }

    /** @param list<callable> $queue */
    private static function notifier(array $queue): TelegramNotifier
    {
        $httpFactory = new HttpFactory();
        $handler     = HandlerStack::create(new MockHandler($queue));
        $httpClient  = new Client(['handler' => $handler]);

        return new TelegramNotifier(
            'https://api.telegram.org/botTOKEN/sendMessage?chat_id=123',
            $httpClient,
            $httpFactory,
            $httpFactory,
            new NullLogger(),
        );
    }

    /**
     * @param list<RequestInterface> $requests
     *
     * @return callable(RequestInterface): Response
     */
    private static function recordResponse(array &$requests): callable
    {
        return static function (RequestInterface $request) use (&$requests): Response {
            $requests[] = $request;

            return new Response();
        };
    }

    /**
     * @param list<RequestInterface> $requests
     *
     * @return callable(RequestInterface): RequestException
     */
    private static function recordFailure(array &$requests): callable
    {
        return static function (RequestInterface $request) use (&$requests): RequestException {
            $requests[] = $request;

            return new RequestException('Nope', $request);
        };
    }
}
