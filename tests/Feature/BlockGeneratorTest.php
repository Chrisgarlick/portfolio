<?php

declare(strict_types=1);

use Cg\Cms\Generator\BlockGenerator;
use Cg\Cms\Generator\FieldSpec;
use Cg\Cms\Schema\Block;
use Illuminate\Support\Facades\Blade;

/*
|------------------------------------------------------------------------------
| cms:block, the block generator
|------------------------------------------------------------------------------
|
| Checked end to end by hand: generate, run the generated test, add the block
| to a page in the editor and preview it. These pin the rest.
|
*/

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/cms-block-'.uniqid();
    mkdir($this->dir);
    copy(base_path('app/Cms/schema.php'), $this->dir.'/schema.php');

    config([
        'cg-cms.schema_path' => $this->dir.'/schema.php',
        'cg-cms.blocks_path' => $this->dir.'/blocks.php',
    ]);
});

afterEach(function (): void {
    array_map('unlink', glob($this->dir.'/*'));
    rmdir($this->dir);
});

function blockGenerator(string $handle = 'testimonial', string $fields = 'quote:textarea:required,name:text,body:richText,photo:media,link:url'): BlockGenerator
{
    return new BlockGenerator($handle, 'Testimonial', FieldSpec::parseList($fields), config('cg-cms.blocks_path'), config('cg-cms.schema_path'));
}

it('plans a blocks file, template and test that all parse, and opens pages to app blocks', function (): void {
    $plan = blockGenerator()->plan();

    expect($plan)->toHaveCount(4);

    foreach ($plan as $file) {
        token_get_all(str_ends_with($file->path, '.blade.php') ? Blade::compileString($file->contents) : $file->contents, TOKEN_PARSE);
    }

    expect($plan[3]->contents)->toContain('->allow([...Block::builtIns(), ...Block::app()])');
});

it('registers generated blocks alongside the built-in ones', function (): void {
    file_put_contents(config('cg-cms.blocks_path'), blockGenerator()->plan()[0]->contents);

    $blocks = Block::app();

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->handle)->toBe('testimonial')
        ->and(collect($blocks[0]->allFields())->pluck('name')->all())->toBe(['quote', 'name', 'body', 'photo', 'link', 'theme']);
});

it('adds a second block to the existing file rather than replacing it', function (): void {
    file_put_contents(config('cg-cms.blocks_path'), blockGenerator()->plan()[0]->contents);
    file_put_contents(config('cg-cms.blocks_path'), blockGenerator('logo-wall', 'heading:text')->plan()[0]->contents);

    expect(collect(Block::app())->pluck('handle')->all())->toBe(['testimonial', 'logo-wall']);
});

it('refuses handles that exist, bad handles, and fields blocks cannot hold', function (array $arguments, string $message): void {
    $this->artisan('cms:block', [...$arguments, '--dry-run' => true])
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'existing' => [['handle' => 'hero'], 'already exists'],
    'bad handle' => [['handle' => 'Logo_Wall'], 'kebab-case'],
    'relation' => [['handle' => 'picks', '--fields' => 'items:relation:project'], 'relation fields'],
    'theme' => [['handle' => 'thing', '--fields' => 'theme:text'], 'theme field already'],
]);

it('writes nothing on a dry run', function (): void {
    $this->artisan('cms:block', ['handle' => 'testimonial', '--fields' => 'quote:textarea', '--dry-run' => true])->assertSuccessful();

    expect(file_exists(config('cg-cms.blocks_path')))->toBeFalse()
        ->and(file_exists(resource_path('views/vendor/cgcms/blocks/testimonial.blade.php')))->toBeFalse();
});
