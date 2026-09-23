<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

interface TemplateEmailSenderInterface
{
    /**
     * @param array<string, mixed> $templateData
     */
    public function sendWithTemplate(
        string $recipientEmail,
        string $subject,
        string $templatePath,
        array $templateData = [],
    ): bool;
}
