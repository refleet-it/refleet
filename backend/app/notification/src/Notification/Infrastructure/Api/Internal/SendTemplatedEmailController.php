<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\TemplateEmailSenderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpTemplateEmailSender, the adapter
 * Organization uses for TemplateEmailSenderInterface — see
 * docs/adr/0001-multiple-kernels.md. templatePath must name one of Notification's own
 * templates; that coupling already existed before this endpoint, it's just crossing a
 * container boundary now instead of a namespace one.
 */
#[Route('/send-templated-email', name: 'internal_send_templated_email', methods: ['POST'])]
final readonly class SendTemplatedEmailController
{
    public function __construct(
        private TemplateEmailSenderInterface $emailSender,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{recipientEmail: string, subject: string, templatePath: string, templateData?: array<string, mixed>} $payload */
        $payload = \json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $sent = $this->emailSender->sendWithTemplate(
            recipientEmail: $payload['recipientEmail'],
            subject: $payload['subject'],
            templatePath: $payload['templatePath'],
            templateData: $payload['templateData'] ?? [],
        );

        return new JsonResponse(['sent' => $sent]);
    }
}
