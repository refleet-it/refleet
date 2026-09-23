<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Service;

interface GitLabTokenEncryptorInterface
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $encoded): string;
}
