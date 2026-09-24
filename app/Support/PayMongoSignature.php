<?php

namespace App\Support;

/**
 * Proving a webhook really came from PayMongo.
 *
 * Without this the webhook endpoint is a public URL that will confirm any booking it is
 * told about. PayMongo signs each delivery with a header shaped
 * `t=<timestamp>,te=<test signature>,li=<live signature>`, where each signature is
 * `HMAC-SHA256(secret, "<timestamp>.<raw body>")`.
 *
 * The *raw* body matters: re-encoding the JSON changes a byte somewhere and the digest
 * stops matching, which looks exactly like a wrong secret.
 */
class PayMongoSignature
{
    /**
     * How far out of step with PayMongo's clock a delivery may be, in seconds.
     *
     * A replayed request is worthless to an attacker once it is stale, so old signatures
     * are refused even when the digest is right.
     */
    public const TOLERANCE_SECONDS = 300;

    /**
     * Whether this header signs this body with this secret.
     */
    public static function verify(?string $header, string $payload, ?string $secret, ?int $tolerance = null): bool
    {
        if (blank($header) || blank($secret)) {
            return false;
        }

        $parts = self::parse($header);
        $timestamp = $parts['t'] ?? null;

        if ($timestamp === null || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > ($tolerance ?? self::TOLERANCE_SECONDS)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        // A test key signs into `te` and a live key into `li`. Both are compared rather
        // than picking one by key prefix, so a mismatched pair fails loudly instead of
        // being waved through by whichever field happened to be read.
        foreach (['te', 'li'] as $field) {
            if (isset($parts[$field]) && hash_equals($expected, $parts[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The signature header split into its comma-separated parts.
     *
     * @return array<string, string>
     */
    private static function parse(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[$key] = $value;
            }
        }

        return $parts;
    }
}
