<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Cg\Cms\Schema\Collection;
use Cg\Cms\Schema\CollectionRegistry;
use Cg\Cms\Schema\Field;
use Cg\Cms\Schema\FieldGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Groups, reusable groups, conditional fields and tabs (plan section 24)
|------------------------------------------------------------------------------
*/

afterEach(fn () => FieldGroup::flush());

function passes(Collection $collection, array $payload): bool
{
    return Validator::make($payload, $collection->validationRules())->passes();
}

it('validates the fields inside a group as one object', function (): void {
    $collection = Collection::make('landing')->fields([
        Field::group('cta')->fields([
            Field::text('label')->required()->maxLength(20),
            Field::url('url'),
        ]),
    ]);

    expect(passes($collection, ['cta' => ['label' => 'Book a call', 'url' => '/contact']]))->toBeTrue()
        ->and(passes($collection, ['cta' => ['url' => '/contact']]))->toBeFalse()
        ->and(passes($collection, ['cta' => ['label' => str_repeat('x', 21)]]))->toBeFalse();
});

it('builds a group from a reusable definition, with its own field objects per use', function (): void {
    FieldGroup::define('cta', fn () => [Field::text('label')->required(), Field::url('url')]);

    $hero = Field::group('hero_cta')->uses('cta');
    $footer = Field::group('footer_cta')->uses('cta');

    expect(array_map(fn (Field $field) => $field->name, $hero->subFields()))->toBe(['label', 'url'])
        ->and($hero->subFields()[0])->not->toBe($footer->subFields()[0]);
});

it('fails loudly when a schema uses a group that was never defined', function (): void {
    Field::group('cta')->uses('missing');
})->throws(InvalidArgumentException::class, 'Unknown field group [missing]');

it('requires a conditional field only while its condition holds', function (): void {
    $collection = Collection::make('work')->fields([
        Field::select('kind')->options(['personal', 'client']),
        Field::text('client_name')->required()->showWhen('kind', 'client'),
    ]);

    expect(passes($collection, ['kind' => 'client']))->toBeFalse()
        ->and(passes($collection, ['kind' => 'client', 'client_name' => 'Acme']))->toBeTrue()
        ->and(passes($collection, ['kind' => 'personal']))->toBeTrue();
});

it('resolves a condition against siblings inside a group', function (): void {
    $collection = Collection::make('landing')->fields([
        Field::group('cta')->fields([
            Field::select('target')->options(['page', 'external']),
            Field::url('external_url')->required()->showWhen('target', 'external'),
        ]),
    ]);

    expect(passes($collection, ['cta' => ['target' => 'external']]))->toBeFalse()
        ->and(passes($collection, ['cta' => ['target' => 'page']]))->toBeTrue();
});

it('tells the editor where each field goes', function (): void {
    $fields = [
        Field::richText('body')->toArray(),
        Field::select('kind')->options(['a'])->toArray(),
        Field::select('layout')->options(['a'])->sidebar(false)->tab('Design')->toArray(),
        Field::text('client_name')->showWhen('kind', ['client', 'agency'])->toArray(),
    ];

    expect($fields[0]['sidebar'])->toBeFalse()
        ->and($fields[1]['sidebar'])->toBeTrue()
        ->and($fields[2])->toMatchArray(['sidebar' => false, 'tab' => 'Design'])
        ->and($fields[3]['condition'])->toBe(['field' => 'kind', 'values' => ['client', 'agency']]);
});

it('renders rich text inside a group on save', function (): void {
    app(CollectionRegistry::class)->register(
        Collection::make('landing')->fields([
            Field::text('title'),
            Field::slug('slug')->from('title'),
            Field::group('intro')->fields([Field::text('heading'), Field::richText('body')]),
        ]),
    );

    $entry = Entry::query()->create([
        'collection' => 'landing',
        'slug' => 'grouped',
        'title' => 'Grouped',
        'data' => ['intro' => ['heading' => 'Hello', 'body' => tiptapParagraph('Inside a group.')]],
    ]);

    expect(data_get($entry->rendered, 'intro.body'))->toBe('<p>Inside a group.</p>');
});
