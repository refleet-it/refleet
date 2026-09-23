<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Unified JSON formatter that uses 'msg' instead of 'message'
 * to be consistent with Caddy logs in Loki/Grafana.
 */
final class UnifiedJsonFormatter extends JsonFormatter
{
    #[\Override]
    public function format(LogRecord $record): string
    {
        $data = parent::format($record);
        $decoded = \json_decode($data, true);

        if (!\is_array($decoded)) {
            return $data;
        }

        if (isset($decoded['message'])) {
            $decoded['msg'] = $decoded['message'];
            unset($decoded['message']);
        }

        $time = $decoded['datetime'] ?? \date('c');
        $channel = $decoded['channel'] ?? 'app';

        $simplified = [
            'msg' => $decoded['msg'] ?? '',
            'level' => $decoded['level_name'] ?? $decoded['level'] ?? 'INFO',
            'time' => $time,
            'ts' => $time,
            'channel' => $channel,
            'logger' => $channel,
        ];

        /** @var array<string, mixed> $context */
        $context = \is_array($decoded['context'] ?? null) ? $decoded['context'] : [];
        /** @var array<string, mixed> $extra */
        $extra = \is_array($decoded['extra'] ?? null) ? $decoded['extra'] : [];

        [$simplified, $context, $extra] = $this->promoteKnownFields($simplified, $context, $extra);

        if ([] !== $context) {
            $simplified['context'] = $context;
        }

        if ([] !== $extra) {
            $simplified['extra'] = $extra;
        }

        return \json_encode($simplified, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * @param array<string, mixed> $simplified
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function promoteKnownFields(array $simplified, array $context, array $extra): array
    {
        $fieldMapping = [
            'user_id' => ['context', 'user_id'],
            'email' => ['context', 'email'],
            'request_id' => ['extra', 'request_id'],
            'trace_id' => ['extra', 'trace_id'],
            'span_id' => ['extra', 'span_id'],
            'ip' => ['extra', 'ip'],
            'method' => ['extra', 'http_method'],
            'url' => ['extra', 'url'],
            'user_agent' => ['extra', 'user_agent'],
        ];

        foreach ($fieldMapping as $targetKey => [$source, $sourceKey]) {
            $sourceArray = 'context' === $source ? $context : $extra;
            if (!isset($sourceArray[$sourceKey])) {
                continue;
            }

            $simplified[$targetKey] = $sourceArray[$sourceKey];
            if ('context' === $source) {
                unset($context[$sourceKey]);
            } else {
                unset($extra[$sourceKey]);
            }
        }

        return [$simplified, $context, $extra];
    }
}
