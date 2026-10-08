<?php

declare(strict_types=1);

use Cg\Cms\Generator\CollectionGenerator;
use Cg\Cms\Generator\FieldSpec;
use Illuminate\Support\Facades\Blade;

/*
|------------------------------------------------------------------------------
| cms:collection, the content type generator
|------------------------------------------------------------------------------
|
| The whole flow (generate, run the generated test, publish an entry in the
| admin, view both pages) was checked by hand against this site. These pin
| the parts that must not regress: parsing, refusing bad input, never
| overwriting, and producing code that parses.
|
*/

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/cms-gen-'.uniqid();
    mkdir($this->dir);
    copy(base_path('app/Cms/schema.php'), $this->dir.'/schema.php');
    copy(base_path('routes/public.php'), $this->dir.'/public.php');

    config([
        'cg-cms.schema_path' => $this->dir.'/schema.php',
        'cg-cms.generator.routes' => $this->dir.'/public.php',
    ]);
});

afterEach(function (): void {
    array_map('unlink', glob($this->dir.'/*'));
    rmdir($this->dir);
});

function generatorFor(string $fields, ?string $route = '/frameworks'): CollectionGenerator
{
    return new CollectionGenerator(
        handle: 'framework',
        label: 'Frameworks',
        fields: FieldSpec::parseList($fields),
        routeBase: $route,
        schemaPath: config('cg-cms.schema_path'),
        routesPath: config('cg-cms.generator.routes'),
    );
}

it('parses field specs, including aliases, options, targets and flags', function (): void {
    $fields = FieldSpec::parseList('summary:textarea,logo:image:required,kind:select:a|b,projects:relation:project:many');

    expect($fields[1])->toMatchArray(['type' => 'media', 'required' => true])
        ->and($fields[2]->options)->toBe(['a', 'b'])
        ->and($fields[3])->toMatchArray(['target' => 'project', 'many' => true])
        ->and($fields[3]->toPhp())->toBe("Field::relation('projects')->to('project')->many()")
        ->and(array_map(fn (FieldSpec $f) => $f->toSpec(), $fields))->toBe([
            'summary:textarea', 'logo:media:required', 'kind:select:a|b', 'projects:relation:project:many',
        ]);
});

it('rejects specs that would produce a broken schema', function (string $spec, string $message): void {
    expect(fn () => FieldSpec::parseList($spec))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unknown type' => ['logo:picture', 'unknown type'],
    'reserved name' => ['title:text', 'added to every collection'],
    'bad name' => ['Logo:text', 'snake_case'],
    'relation without target' => ['projects:relation', 'needs a target'],
    'select without options' => ['kind:select', 'needs options'],
    'duplicate' => ['a:text,a:number', 'listed twice'],
]);

it('produces a schema, controller, routes, templates and test that all parse', function (): void {
    $plan = generatorFor('summary:textarea,logo:media,years:number,website:url,kind:select:a|b,body:richText,live:boolean,projects:relation:project:many')->plan();

    expect($plan)->toHaveCount(6);

    foreach ($plan as $file) {
        $php = str_ends_with($file->path, '.blade.php') ? Blade::compileString($file->contents) : $file->contents;

        // Throws ParseError on anything that is not valid PHP.
        token_get_all($php, TOKEN_PARSE);
    }

    $schema = collect($plan)->firstWhere('path', config('cg-cms.schema_path'))->contents;
    $routes = collect($plan)->firstWhere('path', config('cg-cms.generator.routes'))->contents;

    expect($schema)->toContain("Collection::make('framework')", "->route('/frameworks/{slug}')", "->seoDescriptionFrom('summary')")
        ->and(substr_count($schema, "Collection::make('project')"))->toBe(1)
        ->and($routes)->toContain('use App\Http\Controllers\FrameworkController;', "->name('frameworks.show')");
});

it('loads the generated schema as a working collection', function (): void {
    $plan = generatorFor('summary:textarea,kind:select:a|b')->plan();
    file_put_contents(config('cg-cms.schema_path'), $plan[0]->contents);

    $collections = require config('cg-cms.schema_path');
    $framework = collect($collections)->first(fn ($collection) => $collection->handle === 'framework');

    expect($framework->routePattern())->toBe('/frameworks/{slug}')
        ->and(collect($framework->allFields())->pluck('name')->all())
        ->toBe(['title', 'slug', 'summary', 'kind', 'published_at', 'status', 'seo']);
});

it('makes a data-only collection with no pages', function (): void {
    $generator = generatorFor('value:number', route: null);

    expect($generator->plan())->toHaveCount(1)
        ->and($generator->schemaCode())->not->toContain('->route(')->not->toContain('Field::seo()');
});

it('writes nothing on a dry run', function (): void {
    $before = file_get_contents(config('cg-cms.schema_path'));

    $this->artisan('cms:collection', ['handle' => 'framework', '--fields' => 'summary:textarea', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(file_get_contents(config('cg-cms.schema_path')))->toBe($before)
        ->and(file_exists(app_path('Http/Controllers/FrameworkController.php')))->toBeFalse();
});

it('writes a data-only collection into the schema', function (): void {
    $this->artisan('cms:collection', ['handle' => 'skill', '--routeless' => true, '--fields' => 'level:number', '--force' => true])
        ->assertSuccessful();

    expect(file_get_contents(config('cg-cms.schema_path')))->toContain("Collection::make('skill')", "Field::number('level')->nullable()");
});

it('refuses handles that exist, clash with admin screens, or are malformed', function (string $handle, string $message): void {
    $this->artisan('cms:collection', ['handle' => $handle, '--dry-run' => true])
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'existing' => ['article', 'already exists'],
    'admin screen' => ['media', "admin screen's URL"],
    'malformed' => ['Case-Study', 'snake_case'],
]);

it('refuses a relation to a collection that does not exist', function (): void {
    $this->artisan('cms:collection', ['handle' => 'framework', '--fields' => 'things:relation:nope', '--dry-run' => true])
        ->expectsOutputToContain('not a collection')
        ->assertFailed();
});

it('never overwrites a file that already exists', function (): void {
    $existing = resource_path('views/widgets/index.blade.php');
    mkdir(dirname($existing));
    file_put_contents($existing, 'mine');
    $schemaBefore = file_get_contents(config('cg-cms.schema_path'));

    try {
        $this->artisan('cms:collection', ['handle' => 'widget', '--force' => true])
            ->expectsOutputToContain('Already exists: resources/views/widgets/index.blade.php')
            ->assertFailed();

        expect(file_get_contents($existing))->toBe('mine')
            ->and(file_get_contents(config('cg-cms.schema_path')))->toBe($schemaBefore)
            ->and(file_exists(app_path('Http/Controllers/WidgetController.php')))->toBeFalse();
    } finally {
        unlink($existing);
        rmdir(dirname($existing));
    }
});
