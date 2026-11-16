<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| kit's styles reach a page as one stylesheet
|------------------------------------------------------------------------------
|
| resources/css/kit-site.css imports kit's base, every section and the two
| themes, and the layout loads only that. A section `make sync` brings in has
| to be added there, or its blocks render unstyled.
|
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('imports every kit section, after kit.css and before the themes', function (): void {
    $css = (string) file_get_contents(resource_path('css/kit-site.css'));
    preg_match_all('/@import\s+"([^"]+)";/', $css, $m);
    $imports = $m[1];

    $sections = array_map(
        fn (string $dir): string => '../../kit-blocks/sections/'.basename($dir).'/style.css',
        glob(base_path('kit-blocks/sections/*'), GLOB_ONLYDIR) ?: [],
    );

    expect($sections)->not->toBeEmpty()
        ->and(array_diff($sections, $imports))->toBe([])
        ->and($imports[0])->toBe('../../kit-blocks/kit/kit.css')
        ->and(array_slice($imports, -2))->toBe(['./kit-theme-nightfolio.css', './kit-theme-forms.css']);
});

it('loads no per-section stylesheet on a kit page', function (): void {
    $this->artisan('site:install-content', ['--only' => ['pages'], '--publish' => true])->assertSuccessful();

    $this->get('/how-i-work')
        ->assertOk()
        ->assertSee('kit-site', false)
        ->assertDontSee('/kit-blocks/sections/', false)
        ->assertDontSee('/kit-blocks/kit/kit.css', false);
});
