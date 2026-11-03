<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Domain\Account\Exception\OidcAuthenticationFailedException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The three requests a sign-in makes to the issuer — discovery, signing keys, token exchange —
 * share the same failure shape: anything other than a 200 with a JSON object is a failed
 * sign-in, and the detail belongs in the log rather than in the response.
 *
 * Separate from OidcClient because the client is about the flow and this is about the wire.
 */
final readonly class OidcHttpReader
{
    private const int TIMEOUT_SECONDS = 10;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    public function getRaw(string $url, string $failureReason): string
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => self::TIMEOUT_SECONDS]);

            if (200 !== $response->getStatusCode()) {
                throw new OidcAuthenticationFailedException($failureReason);
            }

            return $response->getContent();
        } catch (OidcAuthenticationFailedException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            $this->logger->error('OIDC request to '.$url.' failed: '.$throwable->getMessage());

            throw new OidcAuthenticationFailedException($failureReason);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonObject(string $url, string $failureReason): array
    {
        return self::decodeJsonObject($this->getRaw($url, $failureReason), $failureReason);
    }

    /**
     * @param array<string, string> $form
     *
     * @return array<string, mixed>
     */
    public function postFormForJsonObject(string $url, array $form, string $rejectedReason, string $unreachableReason): array
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'body' => $form,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new OidcAuthenticationFailedException($rejectedReason);
            }

            $raw = $response->getContent();
        } catch (OidcAuthenticationFailedException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            $this->logger->error('OIDC request to '.$url.' failed: '.$throwable->getMessage());

            throw new OidcAuthenticationFailedException($unreachableReason);
        }

        return self::decodeJsonObject($raw, $rejectedReason);
    }

    /**
     * Decoding is where "an array came back" becomes "an object with string keys came back",
     * which is what every caller actually needs.
     *
     * @return array<string, mixed>
     */
    public static function decodeJsonObject(string $raw, string $failureReason): array
    {
        $decoded = \json_decode($raw, true);

        if (!\is_array($decoded)) {
            throw new OidcAuthenticationFailedException($failureReason);
        }

        return self::asJsonObject($decoded);
    }

    /**
     * @param array<array-key, mixed> $decoded
     *
     * @return array<string, mixed>
     */
    public static function asJsonObject(array $decoded): array
    {
        $object = [];

        foreach ($decoded as $key => $value) {
            $object[(string) $key] = $value;
        }

        return $object;
    }
}
