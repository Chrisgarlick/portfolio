<?php

declare(strict_types=1);

namespace App\Content;

use Cg\Cms\Models\Entry;

/**
 * The only thing a project template is allowed to read.
 *
 * Client work is under NDA to varying degrees. "Be careful what you write" is a
 * discipline problem, and discipline fails on a Tuesday six months from now. So
 * disclosure is enforced here instead: templates receive a presenter, never the
 * raw Entry, and the presenter has no method that returns a client name unless
 * disclosure is 'named'.
 *
 * That is a stronger guarantee than an accessor you could bypass or a comment
 * reminding you not to. There is a test asserting an undisclosed project's
 * client name never appears in the rendered HTML.
 *
 * Three levels:
 *
 *   named        Everything is publishable. Name, links, the lot.
 *   anonymised   A descriptor stands in ("a UK law firm"). No name, no links,
 *                since a live URL identifies the client as surely as the name.
 *   undisclosed  No client information at all. The work is described, the
 *                client is not acknowledged to exist.
 */
final readonly class ProjectPresenter
{
    private function __construct(private Entry $entry) {}

    public static function make(Entry $entry): self
    {
        return new self($entry);
    }

    /** @return array<int, self> */
    public static function collection(iterable $entries): array
    {
        $presented = [];

        foreach ($entries as $entry) {
            $presented[] = new self($entry);
        }

        return $presented;
    }

    /* -----------------------------------------------------------------------
     | Always publishable
     |----------------------------------------------------------------------- */

    public function title(): string
    {
        return $this->entry->title;
    }

    public function url(): string
    {
        return $this->entry->url();
    }

    public function summary(): string
    {
        return (string) $this->entry->value('summary', '');
    }

    public function bodyHtml(): string
    {
        return $this->entry->html('body');
    }

    public function role(): string
    {
        return (string) $this->entry->value('role', '');
    }

    /** @return array<int, string> */
    public function stack(): array
    {
        $stack = (string) $this->entry->value('stack', '');

        if (trim($stack) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $stack))));
    }

    public function outcome(): string
    {
        return (string) $this->entry->value('outcome', '');
    }

    public function year(): ?int
    {
        $year = $this->entry->value('year');

        return $year === null ? null : (int) $year;
    }

    public function kind(): string
    {
        return (string) $this->entry->value('kind', 'personal');
    }

    public function isFeatured(): bool
    {
        return (bool) $this->entry->value('featured', false);
    }

    /**
     * Slugs of the services this work is filed under.
     *
     * @return array<int, string>
     */
    public function services(): array
    {
        return array_values(array_filter((array) $this->entry->value('services', []), 'is_string'));
    }

    /* -----------------------------------------------------------------------
     | Gated on disclosure
     |----------------------------------------------------------------------- */

    public function disclosure(): string
    {
        return (string) $this->entry->value('disclosure', 'named');
    }

    public function isConfidential(): bool
    {
        return $this->disclosure() !== 'named';
    }

    /**
     * How the client may be referred to in public, if at all.
     *
     * Returns null rather than an empty string when there is nothing sayable,
     * so a template has to branch instead of silently rendering a blank label.
     */
    public function clientLabel(): ?string
    {
        return match ($this->disclosure()) {
            'named' => $this->nonEmpty($this->entry->value('client_name')),
            'anonymised' => $this->nonEmpty($this->entry->value('client_descriptor')),
            default => null,
        };
    }

    /**
     * Publishable links.
     *
     * Empty unless disclosure is 'named'. A live URL identifies a client as
     * surely as their name does, and a repo link more so, so anonymising the
     * name while linking the site would defeat the whole exercise.
     *
     * @return array<string, string>
     */
    public function links(): array
    {
        if ($this->isConfidential()) {
            return [];
        }

        return array_filter([
            'Live site' => (string) $this->entry->value('live_url', ''),
            'Repository' => (string) $this->entry->value('repo_url', ''),
        ]);
    }

    private function nonEmpty(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
