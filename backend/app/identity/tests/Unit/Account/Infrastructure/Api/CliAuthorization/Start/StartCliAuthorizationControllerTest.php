<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\CliAuthorization\Start;

use App\Identity\Account\Application\Command\StartCliAuthorization\StartCliAuthorizationCommand;
use App\Identity\Account\Application\Command\StartCliAuthorization\StartedCliAuthorization;
use App\Identity\Account\Infrastructure\Api\CliAuthorization\Start\StartCliAuthorizationController;
use App\Identity\Account\Infrastructure\Api\CliAuthorization\Start\StartCliAuthorizationRequest;
use App\Shared\Domain\Exception\TooManyRequestsException;
use App\Shared\Infrastructure\Security\RateLimiter;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(StartCliAuthorizationController::class)]
final class StartCliAuthorizationControllerTest extends TestCase
{
    #[Test]
    public function returns_the_verification_url_and_polling_secret(): void
    {
        $controller = $this->controller();

        $response = $controller(new StartCliAuthorizationRequest(runnerName: 'my-laptop'), $this->request('203.0.113.10'));

        Assert::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('https://app.example.com/cli/authorize/abc123', $data['verificationUrl']);
        Assert::assertSame('device-secret', $data['deviceSecret']);
        Assert::assertSame(3, $data['pollIntervalSeconds']);
    }

    #[Test]
    public function an_address_that_keeps_starting_logins_is_throttled_without_writing_another_row(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(10))->method('dispatch')->willReturnCallback($this->started(...));
        $controller = $this->controller($bus);

        for ($i = 0; $i < 10; ++$i) {
            $controller(new StartCliAuthorizationRequest(runnerName: 'my-laptop'), $this->request('203.0.113.10'));
        }

        $this->expectException(TooManyRequestsException::class);
        $controller(new StartCliAuthorizationRequest(runnerName: 'my-laptop'), $this->request('203.0.113.10'));
    }

    #[Test]
    public function the_throttle_is_per_address(): void
    {
        $controller = $this->controller();

        for ($i = 0; $i < 10; ++$i) {
            $controller(new StartCliAuthorizationRequest(runnerName: 'my-laptop'), $this->request('203.0.113.10'));
        }

        $response = $controller(new StartCliAuthorizationRequest(runnerName: 'my-laptop'), $this->request('198.51.100.7'));

        Assert::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    private function controller(?MessageBusInterface $bus = null): StartCliAuthorizationController
    {
        if (null === $bus) {
            $bus = $this->createStub(MessageBusInterface::class);
            $bus->method('dispatch')->willReturnCallback($this->started(...));
        }

        return new StartCliAuthorizationController($bus, new RateLimiter(new ArrayAdapter()), 'https://app.example.com/');
    }

    private function started(object $command): Envelope
    {
        Assert::assertInstanceOf(StartCliAuthorizationCommand::class, $command);

        return (new Envelope($command))->with(new HandledStamp(new StartedCliAuthorization(
            userCode: 'abc123',
            deviceSecret: 'device-secret',
            expiresAt: '2026-01-01T00:00:00+00:00',
        ), 'handler'));
    }

    private function request(string $ip): Request
    {
        return new Request(server: ['REMOTE_ADDR' => $ip]);
    }
}
