<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An http(s) URL on a public host, for the free site-audit tool.
 *
 * The site never fetches this URL itself: the Kritano platform does, from its
 * own network, so this is not the SSRF boundary. Refusing localhost, private
 * and link-local addresses is a courtesy that turns a guaranteed failure (and
 * a wasted call against the audit quota) into an immediate, clear message.
 * Hostnames are not resolved, deliberately: a DNS lookup inside the request
 * would block a PHP worker on someone else's resolver.
 */
final class PublicHttpUrl implements ValidationRule
{
    public const MESSAGE = 'Please provide a valid HTTP or HTTPS URL.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > 2048) {
            return false;
        }

        $parts = parse_url($value);

        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        // Credentials in a URL are never needed to audit a public page, and
        // they would be stored and shown back in the results.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|test|invalid)$/', $host) === 1) {
            return false;
        }

        // A public hostname has at least one dot and a plausible TLD.
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $host) === 1;
    }
}
