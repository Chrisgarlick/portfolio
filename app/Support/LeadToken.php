<?php

declare(strict_types=1);

namespace App\Support;

/**
 * "This device has already given an email for a download."
 *
 * Ported from signToken() / verifyToken() in server.ts: the lead id and an
 * expiry, base64url-encoded and HMAC-signed. Carried in the cg_lead cookie so a
 * returning visitor goes straight to the format picker.
 *
 * A token rather than Laravel's encrypted cookies, because the public routes
 * and the /api group deliberately run without the cookie middleware (a cookie
 * jar on every public response would make pages uncacheable). The key is
 * derived from APP_KEY with a purpose string, so rotating the app key logs
 * every device out and nothing else can mint one.
 */
final class LeadToken
{
    public const COOKIE = 'cg_lead';

    /** Readable by JavaScript, carries nothing: it only tells the page to show the picker. */
    public const FLAG_COOKIE = 'cg_lead_flag';

    public const TTL_DAYS = 90;

    public static function sign(string $leadId, int $ttlSeconds = self::TTL_DAYS * 86400): string
    {
        $body = self::encode((string) json_encode(['l' => $leadId, 'e' => time() + $ttlSeconds]));

        return $body.'.'.self::encode(hash_hmac('sha256', $body, self::key(), true));
    }

    /** The lead id, or null for anything missing, forged, malformed or expired. */
    public static function verify(?string $token): ?string
    {
        if ($token === null || substr_count($token, '.') !== 1) {
            return null;
        }

        [$body, $signature] = explode('.', $token);

        $expected = hash_hmac('sha256', $body, self::key(), true);

        if (! hash_equals($expected, self::decode($signature))) {
            return null;
        }

        $payload = json_decode(self::decode($body), true);

        if (! is_array($payload) || ! is_string($payload['l'] ?? null) || ! is_int($payload['e'] ?? null)) {
            return null;
        }

        return $payload['e'] >= time() ? $payload['l'] : null;
    }

    private static function key(): string
    {
        return hash_hmac('sha256', 'cg-lead-token', (string) config('app.key'), true);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }
}
