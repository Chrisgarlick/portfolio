<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Cache\PageCache;
use Cg\Cms\Media\MediaLibrary;
use Cg\Cms\Schema\Block;
use Cg\Cms\Schema\BlockRegistry;
use Cg\Cms\Schema\Field;
use Cg\Cms\Settings\Settings;
use Cg\Cms\Settings\SiteSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| What cg-cms gives kit's sections as blocks (see cg-cms's KIT-BLOCKS.md)
|------------------------------------------------------------------------------
*/

function passesField(Field $field, mixed $value): bool
{
    return Validator::make([$field->name => $value], [$field->name => $field->validationRules()])->passes();
}

it('takes defaults of any JSON type and hands them to the admin cast to the field type', function (): void {
    expect(Field::number('quantity')->default(1)->toArray()['default'])->toBe(1)
        ->and(Field::number('quantity')->default('2.5')->toArray()['default'])->toBe(2.5)
        ->and(Field::number('quantity')->default('many')->toArray()['default'])->toBeNull()
        ->and(Field::boolean('featured')->default(true)->toArray()['default'])->toBeTrue()
        ->and(Field::boolean('featured')->default('1')->toArray()['default'])->toBeTrue()
        ->and(Field::boolean('featured')->default('false')->toArray()['default'])->toBeFalse()
        ->and(Field::select('level')->options(['1', '2'])->default(2)->toArray()['default'])->toBe('2')
        ->and(Field::text('title')->default('Hello')->toArray()['default'])->toBe('Hello')
        ->and(Field::repeater('rows')->default([['label' => 'One']])->toArray()['default'])->toBe([['label' => 'One']])
        ->and(Field::text('title')->default(null)->toArray()['default'])->toBeNull();

    // JSON is what the admin reads: a number and a boolean, not '1'.
    expect(json_encode([Field::number('n')->default('3')->toArray()['default'], Field::boolean('b')->default('1')->toArray()['default']]))
        ->toBe('[3,true]');
});

it('validates a number field against its min and max', function (): void {
    $field = Field::number('progress')->min(0)->max(100);

    expect(passesField($field, 50))->toBeTrue()
        ->and(passesField($field, 0))->toBeTrue()
        ->and(passesField($field, 100))->toBeTrue()
        ->and(passesField($field, -1))->toBeFalse()
        ->and(passesField($field, 101))->toBeFalse()
        ->and(passesField(Field::number('ratio')->min(0.5), 0.25))->toBeFalse()
        ->and($field->toArray())->toMatchArray(['min' => 0, 'max' => 100])
        ->and($field->minRows())->toBeNull();
});

it('still counts rows for a repeater min and max', function (): void {
    $field = Field::repeater('items')->min(1)->max(2)->fields([Field::text('label')]);

    expect(passesField($field, [['label' => 'a']]))->toBeTrue()
        ->and(passesField($field, []))->toBeFalse()
        ->and(passesField($field, [['label' => 'a'], ['label' => 'b'], ['label' => 'c']]))->toBeFalse()
        ->and($field->minRows())->toBe(1)
        ->and($field->minValue())->toBeNull();
});

/*
| Site details from a schema (cg-cms.site_schema)
*/

function useSiteSchema(): void
{
    $path = sys_get_temp_dir().'/cgcms-site-schema-'.getmypid().'.json';
    file_put_contents($path, json_encode(['name' => 'site', 'fields' => [
        'name' => ['type' => 'text', 'label' => 'Business name', 'required' => true, 'max' => 40],
        'phone' => ['type' => 'text', 'label' => 'Phone number', 'max' => 30],
        'review_summary' => ['type' => 'rich-text', 'label' => 'Review summary', 'max' => 80],
        'socials' => ['type' => 'list', 'label' => 'Social profiles', 'max' => 2, 'item' => [
            'network' => ['type' => 'enum', 'options' => ['instagram', 'facebook'], 'default' => 'instagram'],
            'url' => ['type' => 'url', 'label' => 'Profile address', 'required' => true],
        ]],
        'logo' => ['type' => 'image', 'label' => 'Logo', 'alt' => 'optional'],
    ]]));

    config(['cg-cms.site_schema' => $path]);
    app()->forgetInstance(SiteSchema::class);
    app()->forgetInstance(Settings::class);
}

it('maps the site schema to admin fields: a list is a repeater, an image a media field', function (): void {
    useSiteSchema();

    $fields = collect(app(SiteSchema::class)->fields())->keyBy('name');

    expect($fields->keys()->all())->toBe(['name', 'phone', 'review_summary', 'socials', 'logo'])
        ->and($fields['phone']->type)->toBe('text')
        ->and($fields['phone']->maxLengthValue())->toBe(30)
        ->and($fields['phone']->isRequired())->toBeFalse()
        ->and($fields['review_summary']->type)->toBe('textarea')
        ->and($fields['review_summary']->helpText())->toContain('*Stars*')
        ->and($fields['socials']->type)->toBe('repeater')
        ->and($fields['socials']->maxRows())->toBe(2)
        ->and($fields['socials']->subFields()[0]->selectOptions())->toBe(['instagram', 'facebook'])
        ->and($fields['socials']->subFields()[1]->isRequired())->toBeTrue()
        ->and($fields['logo']->type)->toBe('media')
        ->and($fields['logo']->altRequired())->toBeFalse();
});

it('shows the site details on the Settings screen, leaving out what cg-cms already has', function (): void {
    useSiteSchema();
    $this->actingAs(User::factory()->create());

    $props = $this->get('/admin/settings', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ])->assertOk()->json('props.siteDetails');

    expect(array_column($props['fields'], 'name'))->toBe(['phone', 'review_summary', 'socials', 'logo'])
        ->and(array_column($props['fields'], 'type'))->toBe(['text', 'textarea', 'repeater', 'media']);
});

it('saves the site details as settings and Settings::site() returns them', function (): void {
    useSiteSchema();
    $this->actingAs(User::factory()->create());

    $this->put('/admin/settings', [
        'site.name' => 'Acme Plumbing',
        'site_details' => [
            'phone' => ' 01234 567890 ',
            'review_summary' => '**4.9** from 120 reviews',
            'socials' => [['network' => 'instagram', 'url' => 'https://instagram.com/acme']],
            'logo' => null,
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    app()->forgetInstance(Settings::class);

    // toEqual: jsonb keeps an object's keys in its own order.
    expect(app(Settings::class)->site())->toEqual([
        'name' => 'Acme Plumbing',
        'phone' => '01234 567890',
        'review_summary' => '**4.9** from 120 reviews',
        'socials' => [['network' => 'instagram', 'url' => 'https://instagram.com/acme']],
        'logo' => null,
    ]);

    // Emptied, a detail stores nothing and config shows through.
    $this->put('/admin/settings', ['site_details' => ['phone' => '', 'socials' => []]])->assertRedirect();
    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->all())->not->toHaveKey('site.phone')->not->toHaveKey('site.socials');
});

it('validates the site details with the fields\' own rules', function (): void {
    useSiteSchema();
    $this->actingAs(User::factory()->create());

    $this->put('/admin/settings', ['site_details' => [
        'phone' => str_repeat('1', 31),
        'socials' => [['network' => 'myspace', 'url' => '']],
    ]])->assertSessionHasErrors(['site_details.phone', 'site_details.socials.0.network', 'site_details.socials.0.url']);
});

it('returns no site details without a site schema', function (): void {
    config(['cg-cms.site_schema' => null]);
    app()->forgetInstance(SiteSchema::class);

    expect(app(Settings::class)->site())->toBe([]);
});

it('purges the pages that showed the site details when settings are saved, and only those', function (): void {
    $this->actingAs(User::factory()->create());
    $cache = app(PageCache::class);
    $cache->put('/with-footer', '<p>footer</p>', [Settings::SITE_TAG, 'collection:page']);
    $cache->put('/without', '<p>plain</p>', ['collection:page']);

    $this->put('/admin/settings', ['seo.description' => 'Something new.'])->assertRedirect();

    expect($cache->has('/with-footer'))->toBeFalse()
        ->and($cache->has('/without'))->toBeTrue();

    $cache->forget('/without');
});

/*
| Media presets for kit's image sizes
*/

it('has a media preset for every size kit names, so no kit image falls back to full', function (): void {
    $library = app(MediaLibrary::class);

    foreach (['hero', 'half', 'card', 'product'] as $size) {
        expect($library->preset($size))->not->toBeNull("No media preset called {$size}");
    }

    expect($library->preset('hero'))->toBe(['width' => 1600, 'height' => null]);
});

/*
| Block previews: the stylesheets kit blocks need
*/

it('prints the preview stylesheets and what the blocks pushed onto kit-styles', function (): void {
    $dir = sys_get_temp_dir().'/cgcms-preview-views-'.getmypid();
    @mkdir($dir.'/cms', 0o755, true);
    @mkdir($dir.'/stub', 0o755, true);

    // A kit-like block: pushes its stylesheet once, as kit's generated views do.
    file_put_contents($dir.'/stub/styled.blade.php', "@once('stub:styled')\n@push('kit-styles')\n<link rel=\"stylesheet\" href=\"/kit-blocks/sections/styled/style.css\">\n@endpush\n@endonce\n<section class=\"styled\">{{ \$data['title'] ?? '' }}</section>");
    // An application's preview document that includes cg-cms's partial.
    file_put_contents($dir.'/cms/block-preview.blade.php', "<!DOCTYPE html><html><head>@include('cgcms::preview-styles')</head><body>{!! \$body !!}</body></html>");

    View::getFinder()->prependLocation($dir);
    View::getFinder()->flush();
    config(['cg-cms.preview_styles' => ['/kit-blocks/kit/kit.css']]);
    app(BlockRegistry::class)->register(Block::make('stub-styled', 'Styled')->fields([Field::text('title')])->view('stub.styled'));

    $this->actingAs(User::factory()->create());

    $html = $this->postJson('/admin/blocks/preview', ['blocks' => [
        ['type' => 'stub-styled', 'data' => ['title' => 'One']],
        ['type' => 'stub-styled', 'data' => ['title' => 'Two']],
    ]])->assertOk()->getContent();

    $head = substr($html, 0, (int) strpos($html, '</head>'));

    expect($head)->toContain('<link rel="stylesheet" href="/kit-blocks/kit/kit.css">')
        ->toContain('/kit-blocks/sections/styled/style.css')
        ->and(substr_count($html, 'sections/styled/style.css'))->toBe(1)
        ->and(strpos($head, 'kit/kit.css'))->toBeLessThan(strpos($head, 'sections/styled'))
        ->and($html)->toContain('<section class="styled">One</section>')->toContain('Two');

    File::deleteDirectory($dir);
});

/*
| Serving kit's CSS and fonts as static files
*/

it('copies kit blocks\' CSS and fonts into public, never their PHP or JSON', function (): void {
    $source = sys_get_temp_dir().'/cgcms-kit-src-'.getmypid();
    File::deleteDirectory($source);
    foreach (['kit/fonts', 'sections/hero', 'views'] as $dir) {
        File::ensureDirectoryExists($source.'/'.$dir);
    }
    file_put_contents($source.'/kit/kit.css', ':root{}');
    file_put_contents($source.'/kit/kit.php', '<?php');
    file_put_contents($source.'/kit/cgcms.php', '<?php');
    file_put_contents($source.'/kit/site.schema.json', '{}');
    file_put_contents($source.'/kit/fonts/sans.woff2', 'font');
    file_put_contents($source.'/sections/hero/style.css', '.hero{}');
    file_put_contents($source.'/sections/hero/hero.php', '<?php');
    file_put_contents($source.'/sections/hero/schema.json', '{}');
    file_put_contents($source.'/views/hero.blade.php', 'x');
    file_put_contents($source.'/kit-blocks.php', '<?php');

    $target = public_path('kit-blocks-test');
    File::ensureDirectoryExists($target.'/sections/gone');
    file_put_contents($target.'/sections/gone/style.css', '.stale{}');

    $this->artisan('cms:kit-assets', ['source' => $source, '--to' => 'kit-blocks-test'])->assertSuccessful();

    $copied = collect(File::allFiles($target))->map(fn ($file): string => $file->getRelativePathname())->sort()->values()->all();

    expect($copied)->toBe(['kit/fonts/sans.woff2', 'kit/kit.css', 'sections/hero/style.css']);

    // A folder holding anything else is not emptied.
    file_put_contents($target.'/index.php', '<?php');
    $this->artisan('cms:kit-assets', ['source' => $source, '--to' => 'kit-blocks-test'])->assertFailed();
    expect(is_file($target.'/kit/kit.css'))->toBeTrue();

    File::deleteDirectory($target);
    File::deleteDirectory($source);
});
