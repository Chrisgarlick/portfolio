<?php

declare(strict_types=1);

use Cg\Cms\Content\BlockRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|------------------------------------------------------------------------------
| kit's sections render here exactly as kit renders them
|------------------------------------------------------------------------------
|
| tests/fixtures/kit-blocks.json (written by `make sync`) holds every kit demo
| as cg-cms stores it, with the HTML kit's own Go renderer gives it. Each one
| goes through cg-cms's BlockRenderer, so the generated block, its view, kit's
| runtime and kit_content() are all on the path. No site details are set and
| the request is not on any link's path, so nothing is bound or marked current
| beyond what the demo says; images in the demos have no media id, so they
| pass through unresolved.
|
*/

uses(RefreshDatabase::class);

dataset('kit demos', function (): array {
    // Datasets are built before the app boots, so no base_path() here.
    $fixtures = json_decode((string) file_get_contents(dirname(__DIR__).'/fixtures/kit-blocks.json'), true, flags: JSON_THROW_ON_ERROR);

    $cases = [];
    foreach ($fixtures as $fixture) {
        $cases[$fixture['section'].': '.$fixture['title']] = [$fixture];
    }

    return $cases;
});

beforeEach(function (): void {
    config(['kit-blocks.site' => []]);
    $this->get('/__kit-parity-not-a-page');
});

it('renders the demo as kit does', function (array $fixture): void {
    $html = app(BlockRenderer::class)->render([['type' => 'kit-'.$fixture['section'], 'data' => $fixture['data']]]);

    expect(trim($html))->toBe(trim($fixture['html']));
})->with('kit demos');
