<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Service;

use App\Notification\Notification\Domain\Model\Notification;
use App\Shared\Domain\Service\TemplateEmailSenderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Twig\Environment;

final readonly class EmailNotificationService implements TemplateEmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $fromEmail,
        private string $fromName,
        private string $replyToEmail,
    ) {
    }

    public function send(Notification $notification): bool
    {
        try {
            $htmlBody = $notification->body();
            $textBody = $this->convertHtmlToPlainText($htmlBody);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                // Most instances publish no support address; replying to the sender is
                // better than an empty header Symfony would reject anyway.
                ->replyTo('' === $this->replyToEmail ? $this->fromEmail : $this->replyToEmail)
                ->to($notification->recipient()->value())
                ->subject($notification->subject())
                ->text($textBody)
                ->html($htmlBody);

            $this->mailer->send($email);

            $this->logger->info('Email notification sent successfully', [
                'notificationId' => $notification->id()->asString(),
                'recipient' => $notification->recipient()->value(),
                'subject' => $notification->subject(),
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send email notification: transport error', [
                'notificationId' => $notification->id()->asString(),
                'recipient' => $notification->recipient()->value(),
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (RfcComplianceException|\InvalidArgumentException $e) {
            $this->logger->error('Failed to send email notification: invalid email address', [
                'notificationId' => $notification->id()->asString(),
                'recipient' => $notification->recipient()->value(),
                'fromEmail' => $this->fromEmail,
                'replyToEmail' => $this->replyToEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $templateData
     */
    #[\Override]
    public function sendWithTemplate(
        string $recipientEmail,
        string $subject,
        string $templatePath,
        array $templateData = [],
    ): bool {
        try {
            $htmlBody = $this->twig->render($templatePath, $templateData);
            $textBody = $this->convertHtmlToPlainText($htmlBody);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                // Most instances publish no support address; replying to the sender is
                // better than an empty header Symfony would reject anyway.
                ->replyTo('' === $this->replyToEmail ? $this->fromEmail : $this->replyToEmail)
                ->to($recipientEmail)
                ->subject($subject)
                ->text($textBody)
                ->html($htmlBody);

            $this->mailer->send($email);

            $this->logger->info('Email notification sent with template', [
                'recipient' => $recipientEmail,
                'subject' => $subject,
                'template' => $templatePath,
            ]);

            return true;
        } catch (TransportExceptionInterface|\Twig\Error\Error $e) {
            $this->logger->error('Failed to send email notification with template', [
                'recipient' => $recipientEmail,
                'template' => $templatePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (RfcComplianceException|\InvalidArgumentException $e) {
            $this->logger->error('Failed to send email notification with template: invalid email address', [
                'recipient' => $recipientEmail,
                'template' => $templatePath,
                'fromEmail' => $this->fromEmail,
                'replyToEmail' => $this->replyToEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function convertHtmlToPlainText(string $html): string
    {
        // Replace common HTML elements with plain text equivalents
        $text = \preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = \preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = \preg_replace('/<\/div>/i', "\n", $text) ?? $text;
        $text = \preg_replace('/<\/tr>/i', "\n", $text) ?? $text;
        $text = \preg_replace('/<\/h[1-6]>/i', "\n\n", $text) ?? $text;

        // Extract link URLs: <a href="URL">text</a> -> text (URL)
        $text = \preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2 ($1)', $text) ?? $text;

        $text = \strip_tags($text);

        // Decode HTML entities
        $text = \html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = \preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = \preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return \trim($text);
    }
}
