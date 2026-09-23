<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Infrastructure\Service;

use App\Fixtures\Factory\Notification\NotificationFactory;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Notification\Notification\Infrastructure\Service\EmailNotificationService;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(EmailNotificationService::class)]
#[UsesClass(Notification::class)]
final class EmailNotificationServiceTest extends TestCase
{
    use Factories;

    private MailerInterface&MockObject $mailer;

    private Environment&MockObject $twig;

    private LoggerInterface&MockObject $logger;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function send_sends_email_and_logs_success_with_converted_text_body(): void
    {
        // Arrange
        $notification = NotificationFactory::new()->withoutPersisting()->create([
            'recipient' => EmailAddress::fromString('recipient@example.com'),
            'subject' => 'Welcome',
            'body' => '<p>Hello<br>Visit <a href="https://example.com">Example</a></p>',
        ]);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $email): bool {
                Assert::assertSame('noreply@refleet.test', $email->getFrom()[0]->getAddress());
                Assert::assertSame('Refleet', $email->getFrom()[0]->getName());
                Assert::assertSame('support@refleet.test', $email->getReplyTo()[0]->getAddress());
                Assert::assertSame('recipient@example.com', $email->getTo()[0]->getAddress());
                Assert::assertSame('Welcome', $email->getSubject());
                Assert::assertSame('<p>Hello<br>Visit <a href="https://example.com">Example</a></p>', $email->getHtmlBody());
                Assert::assertSame("Hello\nVisit Example (https://example.com)", $email->getTextBody());

                return true;
            }));

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Email notification sent successfully',
                $this->callback(static function (array $context) use ($notification): bool {
                    Assert::assertSame($notification->id()->asString(), $context['notificationId']);
                    Assert::assertSame('recipient@example.com', $context['recipient']);
                    Assert::assertSame('Welcome', $context['subject']);

                    return true;
                }),
            );

        $service = new EmailNotificationService($this->mailer, $this->twig, $this->logger, 'noreply@refleet.test', 'Refleet', 'support@refleet.test');

        // Act
        $result = $service->send($notification);

        // Assert
        Assert::assertTrue($result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function send_returns_false_and_logs_transport_error_when_mailer_fails(): void
    {
        // Arrange
        $notification = NotificationFactory::new()->withoutPersisting()->create([
            'recipient' => EmailAddress::fromString('recipient@example.com'),
            'subject' => 'Transport failure',
            'body' => '<p>Body</p>',
        ]);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP unavailable'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send email notification: transport error',
                $this->callback(static function (array $context) use ($notification): bool {
                    Assert::assertSame($notification->id()->asString(), $context['notificationId']);
                    Assert::assertSame('recipient@example.com', $context['recipient']);
                    Assert::assertSame('SMTP unavailable', $context['error']);

                    return true;
                }),
            );

        $service = new EmailNotificationService($this->mailer, $this->twig, $this->logger, 'noreply@refleet.test', 'Refleet', 'support@refleet.test');

        // Act
        $result = $service->send($notification);

        // Assert
        Assert::assertFalse($result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function send_returns_false_and_logs_invalid_email_error_for_invalid_sender_configuration(): void
    {
        // Arrange
        $notification = NotificationFactory::new()->withoutPersisting()->create([
            'recipient' => EmailAddress::fromString('recipient@example.com'),
            'subject' => 'Subject',
            'body' => '<p>Body</p>',
        ]);

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send email notification: invalid email address',
                $this->callback(static function (array $context) use ($notification): bool {
                    Assert::assertSame($notification->id()->asString(), $context['notificationId']);
                    Assert::assertSame('recipient@example.com', $context['recipient']);
                    Assert::assertSame('invalid-from-email', $context['fromEmail']);
                    Assert::assertSame('support@refleet.test', $context['replyToEmail']);
                    Assert::assertIsString($context['error']);

                    return true;
                }),
            );

        $service = new EmailNotificationService(
            $this->mailer,
            $this->twig,
            $this->logger,
            'invalid-from-email',
            'Refleet',
            'support@refleet.test',
        );

        // Act
        $result = $service->send($notification);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function send_with_template_sends_rendered_email_and_logs_success(): void
    {
        // Arrange
        $templatePath = 'emails/welcome.html.twig';
        $templateData = ['name' => 'Jane'];
        $htmlBody = '<p>Hello<br>Visit <a href="https://example.com">Example</a></p>';

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with($templatePath, $templateData)
            ->willReturn($htmlBody);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $email) use ($htmlBody): bool {
                Assert::assertSame('noreply@refleet.test', $email->getFrom()[0]->getAddress());
                Assert::assertSame('support@refleet.test', $email->getReplyTo()[0]->getAddress());
                Assert::assertSame('recipient@example.com', $email->getTo()[0]->getAddress());
                Assert::assertSame('Template subject', $email->getSubject());
                Assert::assertSame($htmlBody, $email->getHtmlBody());
                Assert::assertSame("Hello\nVisit Example (https://example.com)", $email->getTextBody());

                return true;
            }));

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Email notification sent with template', [
                'recipient' => 'recipient@example.com',
                'subject' => 'Template subject',
                'template' => $templatePath,
            ]);

        $service = new EmailNotificationService($this->mailer, $this->twig, $this->logger, 'noreply@refleet.test', 'Refleet', 'support@refleet.test');

        // Act
        $result = $service->sendWithTemplate(
            'recipient@example.com',
            'Template subject',
            $templatePath,
            $templateData,
        );

        // Assert
        Assert::assertTrue($result);
    }

    #[Test]
    public function send_with_template_returns_false_and_logs_error_when_template_rendering_fails(): void
    {
        // Arrange
        $templatePath = 'emails/missing.html.twig';

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with($templatePath, [])
            ->willThrowException(new RuntimeError('Template failed'));

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to send email notification with template', [
                'recipient' => 'recipient@example.com',
                'template' => $templatePath,
                'error' => 'Template failed',
            ]);

        $service = new EmailNotificationService($this->mailer, $this->twig, $this->logger, 'noreply@refleet.test', 'Refleet', 'support@refleet.test');

        // Act
        $result = $service->sendWithTemplate('recipient@example.com', 'Subject', $templatePath);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function send_with_template_returns_false_and_logs_invalid_email_error_for_invalid_recipient(): void
    {
        // Arrange
        $templatePath = 'emails/welcome.html.twig';

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with($templatePath, [])
            ->willReturn('<p>Body</p>');

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send email notification with template: invalid email address',
                $this->callback(static function (array $context) use ($templatePath): bool {
                    Assert::assertSame('invalid-recipient', $context['recipient']);
                    Assert::assertSame($templatePath, $context['template']);
                    Assert::assertSame('noreply@refleet.test', $context['fromEmail']);
                    Assert::assertSame('support@refleet.test', $context['replyToEmail']);
                    Assert::assertIsString($context['error']);

                    return true;
                }),
            );

        $service = new EmailNotificationService($this->mailer, $this->twig, $this->logger, 'noreply@refleet.test', 'Refleet', 'support@refleet.test');

        // Act
        $result = $service->sendWithTemplate('invalid-recipient', 'Subject', $templatePath);

        // Assert
        Assert::assertFalse($result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }
}
