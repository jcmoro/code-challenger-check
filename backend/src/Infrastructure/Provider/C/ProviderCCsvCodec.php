<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\C;

/**
 * Encoder/decoder for Provider C's wire format: a two-line CSV (one header
 * row + one data row). Used in both directions:
 * - Client side: encode the outgoing request, decode the response body.
 * - Simulator side (controller): decode the incoming request, encode the
 *   response (success or error).
 *
 * Centralised here so the format lives in one file — neither side can drift
 * from the other.
 */
final readonly class ProviderCCsvCodec
{
    /**
     * Decode a two-line CSV body into an associative array keyed by header.
     * Returns null on any structural problem (wrong row count, mismatched
     * columns) so callers can map it to a 4xx without exception handling.
     *
     * @return array<string, string>|null
     */
    public function decodeRow(string $body): ?array
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", trim($body))),
            static fn(string $l): bool => '' !== $l,
        ));

        if (2 !== \count($lines)) {
            return null;
        }

        $headers = array_map(
            static fn(?string $v): string => (string) $v,
            str_getcsv($lines[0], escape: '\\'),
        );
        $values = array_map(
            static fn(?string $v): string => (string) $v,
            str_getcsv($lines[1], escape: '\\'),
        );

        if (\count($headers) !== \count($values)) {
            return null;
        }

        return array_combine($headers, $values);
    }

    /**
     * Encode an associative row as a two-line CSV (header + data + trailing
     * newline). Values are stringified verbatim — keep them simple (ints,
     * floats with locale-independent representation, ASCII strings without
     * commas/quotes).
     *
     * @param array<string, string|int|float> $row
     */
    public function encodeRow(array $row): string
    {
        $headers = implode(',', array_keys($row));
        $values = implode(',', array_map(static fn(string|int|float $v): string => (string) $v, $row));

        return "{$headers}\n{$values}\n";
    }
}
