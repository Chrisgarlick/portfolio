<?php

declare(strict_types=1);

namespace App\Support;

/**
 * How well a diagnostic answer set fits a build, scored on the server.
 *
 * Ported exactly from scoreFit() in diagnostic.astro, which ran in the browser
 * and posted its own verdict: anyone could send `fitTier: high` and land in the
 * high-fit inbox. The client no longer scores anything; it shows what this
 * returns.
 *
 * Business type is asked but deliberately not scored, as on the live site: it
 * shapes the follow-up, not the fit.
 */
final readonly class FitScore
{
    public const HIGH = 'high';

    public const MEDIUM = 'medium';

    public const LOW = 'low';

    private function __construct(
        public int $score,
        public string $tier,
    ) {}

    /** @param array<string, mixed> $answers */
    public static function from(array $answers): self
    {
        $score = match ($answers['hours'] ?? null) {
            '10h+' => 3,
            '2-10h' => 2,
            default => 1,
        };

        if (mb_strlen(trim((string) ($answers['task'] ?? ''))) >= 20) {
            $score++;
        }

        if (trim((string) ($answers['stack'] ?? '')) !== '') {
            $score++;
        }

        $priority = (string) ($answers['priority'] ?? '');

        if ($priority === 'All three') {
            $score += 2;
        } elseif ($priority !== '') {
            $score++;
        }

        $tier = match (true) {
            $score >= 6 => self::HIGH,
            $score >= 4 => self::MEDIUM,
            default => self::LOW,
        };

        return new self($score, $tier);
    }

    public function isHigh(): bool
    {
        return $this->tier === self::HIGH;
    }

    /** @return array{score: int, tier: string} */
    public function toArray(): array
    {
        return ['score' => $this->score, 'tier' => $this->tier];
    }
}
