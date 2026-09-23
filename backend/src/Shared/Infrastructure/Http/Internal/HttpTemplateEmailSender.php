<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\TemplateEmailSenderInterface;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * TemplateEmailSenderInterface adapter for every context but Notification, which
 * implements it directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpTemplateEmailSender implements TemplateEmailSenderInterface
{
    private string $notificationInternalUrl;

    public function __construct(
        private InternalApiClient $client,
    ) {
        $url = $_ENV['NOTIFICATION_INTERNAL_URL'] ?? throw new \InvalidArgumentException('NOTIFICATION_INTERNAL_URL environment variable is not set');
        if (!\is_string($url) || '' === $url) {
            throw new \InvalidArgumentException('NOTIFICATION_INTERNAL_URL must be a non-empty string');
        }

        $this->notificationInternalUrl = $url;
    }

    #[\Override]
    public function sendWithTemplate(
        string $recipientEmail,
        string $subject,
        string $templatePath,
        array $templateData = [],
    ): bool {
        $data = $this->client->post($this->notificationInternalUrl, '/internal/send-templated-email', [
            'recipientEmail' => $recipientEmail,
            'subject' => $subject,
            'templatePath' => $templatePath,
            'templateData' => $templateData,
        ]);

        return true === ($data['sent'] ?? false);
    }
}
