<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * The AI readiness audit form, as declared in resources/forms/audit-form.yml.
 *
 * One definition for both ends: the /audit page renders its steps from it and
 * AuditIntakeController validates against it. The live site compiled the YAML
 * to JSON with a script and committed both, which is a build step whose only
 * failure mode is the two drifting apart.
 *
 * Parsed once per process. The file is small and changes only on deploy.
 */
final class AuditForm
{
    /** @var array<string, mixed>|null */
    private static ?array $definition = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return self::$definition ??= (array) Yaml::parseFile(resource_path('forms/audit-form.yml'));
    }

    /** Recorded against every submission, so a request can be matched to the notice it was made under. */
    public function privacyNoticeVersion(): string
    {
        return (string) ($this->definition()['privacy_notice_version'] ?? '');
    }

    /** @return array<int, array{value: string, label: string}> */
    public function sectors(): array
    {
        return array_values((array) ($this->definition()['sectors'] ?? []));
    }

    /** @return array<int, string> */
    public function sectorValues(): array
    {
        return array_column($this->sectors(), 'value');
    }

    /** @return array<int, array<string, mixed>> */
    public function steps(): array
    {
        return array_values((array) ($this->definition()['steps'] ?? []));
    }

    /**
     * Fields every submission must have, whatever the sector, in form order.
     *
     * The required fields of the unconditional steps. The live server kept the
     * same list as a hardcoded constant beside the YAML; deriving it means a
     * field made required in the form is required by the server as well.
     *
     * @return array<int, string>
     */
    public function universalRequired(): array
    {
        $required = [];

        foreach ($this->steps() as $step) {
            foreach ((array) ($step['fields'] ?? []) as $field) {
                if (! empty($field['required'])) {
                    $required[] = (string) $field['name'];
                }
            }
        }

        return $required;
    }

    /**
     * Every field name the form can submit, across all sectors.
     *
     * @return array<int, string>
     */
    public function fieldNames(): array
    {
        $names = [];

        foreach ($this->steps() as $step) {
            foreach ((array) ($step['fields'] ?? []) as $field) {
                $names[] = (string) $field['name'];
            }

            foreach ((array) ($step['fieldsBySector'] ?? []) as $fields) {
                foreach ((array) $fields as $field) {
                    $names[] = (string) $field['name'];
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Options for a field as value/label pairs, resolving optionsFrom.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, array{value: string, label: string}>
     */
    public function options(array $field): array
    {
        if (($field['optionsFrom'] ?? null) === 'sectors') {
            return $this->sectors();
        }

        return array_map(
            fn ($option): array => is_array($option)
                ? ['value' => (string) $option['value'], 'label' => (string) $option['label']]
                : ['value' => (string) $option, 'label' => (string) $option],
            (array) ($field['options'] ?? []),
        );
    }
}
