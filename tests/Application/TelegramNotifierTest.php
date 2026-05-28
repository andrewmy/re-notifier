<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\TelegramNotifier;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

final class TelegramNotifierTest extends TestCase
{
    public function testPhotoMessageUsesSendPhotoWithCaption(): void
    {
        $history  = [];
        $notifier = self::notifier([new Response()], $history);

        $notifier->send(
            "https://www.ss.com/msg/example.html\nBrivibas\nK: 4 | m2: 90 | EUR: 250 000",
            'https://i.ss.com/gallery/example.t.jpg',
        );

        self::assertCount(1, $history);
        self::assertSame(
            'https://api.telegram.org/botTOKEN/sendPhoto?chat_id=123',
            (string) $history[0]['request']->getUri(),
        );
        self::assertSame(
            'photo=https%3A%2F%2Fi.ss.com%2Fgallery%2Fexample.t.jpg&caption=https%3A%2F%2Fwww.ss.com%2Fmsg%2Fexample.html%0ABrivibas%0AK%3A+4+%7C+m2%3A+90+%7C+EUR%3A+250+000',
            (string) $history[0]['request']->getBody(),
        );
    }

    public function testPhotoFailureFallsBackToTextMessage(): void
    {
        $history  = [];
        $notifier = self::notifier([
            new RequestException('Nope', new Request('POST', 'https://api.telegram.org/botTOKEN/sendPhoto?chat_id=123')),
            new Response(),
        ], $history);

        $notifier->send('https://www.ss.com/msg/example.html', 'https://i.ss.com/gallery/example.t.jpg');

        self::assertCount(2, $history);
        self::assertSame(
            'https://api.telegram.org/botTOKEN/sendPhoto?chat_id=123',
            (string) $history[0]['request']->getUri(),
        );
        self::assertSame(
            'https://api.telegram.org/botTOKEN/sendMessage?chat_id=123',
            (string) $history[1]['request']->getUri(),
        );
        self::assertSame(
            'text=https%3A%2F%2Fwww.ss.com%2Fmsg%2Fexample.html',
            (string) $history[1]['request']->getBody(),
        );
    }

    public function testMissingImageSendsTextMessage(): void
    {
        $history  = [];
        $notifier = self::notifier([new Response()], $history);

        $notifier->send('https://www.ss.com/msg/example.html', null);

        self::assertCount(1, $history);
        self::assertSame(
            'https://api.telegram.org/botTOKEN/sendMessage?chat_id=123',
            (string) $history[0]['request']->getUri(),
        );
        self::assertSame(
            'text=https%3A%2F%2Fwww.ss.com%2Fmsg%2Fexample.html',
            (string) $history[0]['request']->getBody(),
        );
    }

    /**
     * @param list<Response|RequestException>        $queue
     * @param list<array{request: RequestInterface}> $history
     */
    private static function notifier(array $queue, array &$history): TelegramNotifier
    {
        $httpFactory = new HttpFactory();
        $handler     = HandlerStack::create(new MockHandler($queue));
        $handler->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handler]);

        return new TelegramNotifier(
            'https://api.telegram.org/botTOKEN/sendMessage?chat_id=123',
            $httpClient,
            $httpFactory,
            $httpFactory,
            new NullLogger(),
        );
    }
}
