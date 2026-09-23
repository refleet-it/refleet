<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

/**
 * Creates the account behind an accepted invitation. Synchronous on purpose: the person is
 * waiting on the response, and failures (an address already registered, a rejected
 * password) have to reach them instead of disappearing into a queue.
 */
interface InvitedAccountRegistrarInterface
{
    /**
     * The invitation link, sent only to that address, already proves ownership — so the
     * account is created without a separate email verification step.
     *
     * @return string id of the created account, so the caller can attach its own records to
     *                it instead of waiting for the account-created announcement to arrive
     */
    public function registerFromInvitation(string $email, string $plainPassword): string;
}
