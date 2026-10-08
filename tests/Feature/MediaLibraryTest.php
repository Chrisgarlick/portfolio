<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Media\CropGeometry;
use Cg\Cms\Media\MediaLibrary;
use Cg\Cms\Models\Media;
use Cg\Cms\Seo\SeoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Step 5: the media library
|------------------------------------------------------------------------------
|
| Upload, variants, alt text, focal point and usage. Variants are written to a
| test directory under public/ rather than a fake disk, because what is being
| asserted is that nginx would find a real file at the URL the page emits.
|
*/

beforeEach(function (): void {
    Storage::fake('local');

    $this->actingAs(User::factory()->create());
});

afterEach(function (): void {
    File::deleteDirectory(public_path('media-test'));
});

function uploadImage(string $name = 'photo.jpg', int $width = 1600, int $height = 1000): TestResponse
{
    return test()->postJson('/admin/media', ['file' => detailedImage($name, $width, $height)]);
}

/**
 * A JPEG with something in it.
 *
 * UploadedFile::fake()->image() is a single flat colour, so every crop of it
 * is identical and a test that moves the focal point proves nothing. Each
 * call also differs by a pixel, so the duplicate check does not merge them.
 */
function detailedImage(string $name, int $width, int $height): UploadedFile
{
    static $calls = 0;

    $image = imagecreatetruecolor($width, $height);

    for ($x = 0; $x < $width; $x += 10) {
        $shade = (int) (255 * $x / max(1, $width));
        imagefilledrectangle($image, $x, 0, $x + 9, $height, (int) imagecolorallocate($image, $shade, 80, 255 - $shade));
    }

    imagesetpixel($image, 0, 0, (int) imagecolorallocate($image, ++$calls % 255, 0, 0));

    $path = tempnam(sys_get_temp_dir(), 'cgimg').'.jpg';
    imagejpeg($image, $path, 90);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

function mediaAdminGet(string $url): TestResponse
{
    return test()->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ]);
}

/*
| Upload and processing
*/

it('stores the original privately and writes every variant', function (): void {
    $response = uploadImage()->assertCreated();

    $media = Media::query()->where('uuid', $response->json('id'))->firstOrFail();

    // The original is on the private disk, never under public/.
    Storage::disk('local')->assertExists($media->path);
    expect($media->path)->not->toContain('public');

    // The queue is sync in tests, so processing has already run.
    expect($media->fresh()->processed_at)->not->toBeNull();

    $library = app(MediaLibrary::class);

    foreach (array_keys($library->presets()) as $preset) {
        foreach ($library->formatsFor($media) as $format) {
            expect(public_path(ltrim($media->url($preset, $format), '/')))->toBeFile();
        }
    }

    [$width, $height] = getimagesize(public_path(ltrim($media->url('card'), '/')));
    expect([$width, $height])->toBe([600, 400]);
});

it('returns the existing image when the same file is uploaded twice', function (): void {
    $file = detailedImage('same.jpg', 800, 600);
    $copy = new UploadedFile($file->getRealPath(), 'same-again.jpg', 'image/jpeg', null, true);

    $first = $this->postJson('/admin/media', ['file' => $file])->assertCreated();
    $second = $this->postJson('/admin/media', ['file' => $copy])->assertOk();

    expect($second->json('id'))->toBe($first->json('id'))
        ->and(Media::query()->count())->toBe(1);
});

it('reads the type from the file rather than trusting the name', function (): void {
    $fake = UploadedFile::fake()->createWithContent('innocent.jpg', '<?php echo "hello";');

    $this->postJson('/admin/media', ['file' => $fake])->assertStatus(422)->assertJsonValidationErrors('file');

    expect(Media::query()->count())->toBe(0);
});

it('refuses an SVG, which is a document that can carry script', function (): void {
    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    $this->postJson('/admin/media', ['file' => $svg])->assertStatus(422);
});

it('caps an oversized original before generating anything from it', function (): void {
    config(['cg-cms.media.max_dimension' => 1000]);

    $media = Media::query()->where('uuid', uploadImage('huge.jpg', 3000, 1500)->json('id'))->firstOrFail();

    expect([$media->width, $media->height])->toBe([1000, 500]);

    [$width] = getimagesize(Storage::disk('local')->path($media->path));
    expect($width)->toBe(1000);
});

/*
| The public variant route
*/

it('generates a missing variant on first request and leaves a file for nginx', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();
    app(MediaLibrary::class)->purgeVariants($media);

    $path = public_path(ltrim($media->url('thumb'), '/'));
    expect($path)->not->toBeFile();

    $response = $this->get($media->url('thumb'))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($path)->toBeFile();

    // An image with Set-Cookie cannot be cached at the edge.
    expect($response->headers->getCookies())->toBe([]);
});

it('404s presets and formats that were never configured', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();

    // Otherwise every made-up URL would be a request to encode a new file.
    $this->get("/media-test/{$media->uuid}/made-up.jpg")->assertNotFound();
    $this->get('/media-test/00000000-0000-0000-0000-000000000000/thumb.jpg')->assertNotFound();

    // A JPEG source has no PNG fallback, so asking for one is not a variant.
    $this->get("/media-test/{$media->uuid}/thumb.png")->assertNotFound();
});

it('never upscales, and keeps the focal point in frame', function (): void {
    // A small original asked for a large preset gives the largest crop of the
    // right shape it has, at its own resolution.
    expect(CropGeometry::output(400, 400, 1200, 630))->toBe(['width' => 400, 'height' => 210]);

    // A focal point on the far right of a wide image moves the crop as far
    // right as the image allows, and no further.
    [$x, , $width] = CropGeometry::box(2000, 1000, 1000, 1000, 1.0, 0.5);
    expect($x)->toBe(1000)->and($width)->toBe(1000);

    [$x] = CropGeometry::box(2000, 1000, 1000, 1000, 0.0, 0.5);
    expect($x)->toBe(0);
});

/*
| Alt text, focal point, deletion
*/

it('does not clear the alt text when only the focal point is sent', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();
    $media->forceFill(['alt' => 'A harbour at dawn'])->save();

    $this->put("/admin/media/{$media->uuid}", ['focal_x' => 0.2, 'focal_y' => 0.8])->assertRedirect();

    $media->refresh();

    expect($media->alt)->toBe('A harbour at dawn')
        ->and($media->focal_x)->toBe(0.2)
        ->and($media->focal_y)->toBe(0.8);
});

it('regenerates variants around a new focal point', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();
    $card = public_path(ltrim($media->url('card'), '/'));
    $before = md5_file($card);

    $this->put("/admin/media/{$media->uuid}", ['focal_x' => 0.0, 'focal_y' => 0.0])->assertRedirect();

    expect($card)->toBeFile()
        ->and(md5_file($card))->not->toBe($before);
});

it('refuses to delete an image that is still in use', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();

    makeArticle('uses-it', 'Uses the image', [
        'data' => [
            'excerpt' => 'x',
            'body' => tiptapParagraph('Body.'),
            'featured_image' => ['id' => $media->uuid, 'alt' => 'A harbour'],
        ],
    ]);

    $this->delete("/admin/media/{$media->uuid}")->assertSessionHas('error');

    expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();
});

it('deletes the original and every variant of an unused image', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();
    $directory = dirname(public_path(ltrim($media->url('card'), '/')));

    $this->delete("/admin/media/{$media->uuid}")->assertRedirect('/admin/media');

    expect(Media::query()->count())->toBe(0)
        ->and($directory)->not->toBeDirectory();

    Storage::disk('local')->assertMissing($media->path);
});

it('finds usage at any depth, including inside blocks and SEO', function (): void {
    $library = app(MediaLibrary::class);

    $inBlock = Media::query()->where('uuid', uploadImage('a.jpg', 300, 300)->json('id'))->firstOrFail();
    $inSeo = Media::query()->where('uuid', uploadImage('b.jpg', 310, 300)->json('id'))->firstOrFail();
    $unused = Media::query()->where('uuid', uploadImage('c.jpg', 320, 300)->json('id'))->firstOrFail();

    makePage([[
        'type' => 'hero',
        'data' => ['heading' => 'Hello', 'image' => ['id' => $inBlock->uuid, 'alt' => 'x']],
    ]]);

    makeArticle('seo-image', 'Has an OG image', [
        'seo' => ['og_image' => ['id' => $inSeo->uuid, 'alt' => 'y']],
    ]);

    expect($library->usageCounts([$inBlock->uuid, $inSeo->uuid, $unused->uuid]))->toBe([
        $inBlock->uuid => 1,
        $inSeo->uuid => 1,
        $unused->uuid => 0,
    ]);

    expect($library->usage($inBlock))->toHaveCount(1);
});

/*
| The admin screen
*/

it('pages the grid, filters by missing alt text, and counts usage', function (): void {
    $described = Media::query()->where('uuid', uploadImage('described.jpg', 300, 200)->json('id'))->firstOrFail();
    $described->forceFill(['alt' => 'Described'])->save();
    uploadImage('bare.jpg', 301, 200);

    $all = mediaAdminGet('/admin/media')->assertOk();
    expect($all->json('props.media.total'))->toBe(2)
        ->and($all->json('props.media.data.0.usage'))->toBe(0);

    $bare = mediaAdminGet('/admin/media?missingAlt=1');
    expect($bare->json('props.media.total'))->toBe(1)
        ->and($bare->json('props.media.data.0.filename'))->toBe('bare.jpg');

    $selected = mediaAdminGet('/admin/media?selected='.$described->uuid);
    expect($selected->json('props.selected.alt'))->toBe('Described')
        ->and($selected->json('props.selected.usedBy'))->toBe([]);
});

it('filters to unused and recent images, counts each filter, and sorts', function (): void {
    $used = Media::query()->where('uuid', uploadImage('used.jpg', 300, 200)->json('id'))->firstOrFail();
    $old = Media::query()->where('uuid', uploadImage('old.jpg', 900, 600)->json('id'))->firstOrFail();
    $old->forceFill(['created_at' => now()->subDays(60)])->save();

    makeArticle('with-image', 'With an image', [
        'data' => ['cover' => ['id' => $used->uuid, 'alt' => 'In use']],
    ]);

    $page = mediaAdminGet('/admin/media?filter=unused')->assertOk();

    expect($page->json('props.media.data.*.filename'))->toBe(['old.jpg'])
        ->and($page->json('props.counts'))->toMatchArray(['all' => 2, 'unused' => 1, 'missing-alt' => 2, 'recent' => 1])
        ->and(mediaAdminGet('/admin/media?filter=recent')->json('props.media.data.*.filename'))->toBe(['used.jpg'])
        ->and(mediaAdminGet('/admin/media?sort=largest')->json('props.media.data.0.filename'))->toBe('old.jpg')
        ->and(mediaAdminGet('/admin/media?sort=name')->json('props.media.data.*.filename'))->toBe(['old.jpg', 'used.jpg']);
});

it('ignores filter and sort values it does not know', function (): void {
    uploadImage('any.jpg', 300, 200);

    $page = mediaAdminGet('/admin/media?filter=nonsense&sort=drop-table')->assertOk();

    expect($page->json('props.filters'))->toMatchArray(['filter' => 'all', 'sort' => 'newest'])
        ->and($page->json('props.media.total'))->toBe(1);
});

/*
| Using an image
*/

it('requires alt text on a media field that declares requireAlt', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();
    $article = makeArticle('needs-alt', 'An article needing alt text on its image');

    $this->put("/admin/article/{$article->id}", [
        'title' => $article->title,
        'slug' => $article->slug,
        'status' => 'draft',
        'body' => tiptapParagraph('Body.'),
        'featured_image' => ['id' => $media->uuid, 'alt' => ''],
    ])->assertSessionHasErrors('featured_image.alt');
});

it('refuses a reference to an image that does not exist', function (): void {
    $article = makeArticle('ghost', 'An article pointing at nothing');

    $this->put("/admin/article/{$article->id}", [
        'title' => $article->title,
        'slug' => $article->slug,
        'status' => 'draft',
        'body' => tiptapParagraph('Body.'),
        'featured_image' => ['id' => '00000000-0000-0000-0000-000000000000', 'alt' => 'Ghost'],
    ])->assertSessionHasErrors('featured_image.id');
});

it('renders a picture with dimensions and only the formats this server can encode', function (): void {
    $media = Media::query()->where('uuid', uploadImage('hero.jpg', 1600, 1000)->json('id'))->firstOrFail();

    $html = Blade::render('<x-cms-image :value="$value" preset="card" />', [
        'value' => ['id' => $media->uuid, 'alt' => 'A harbour at dawn'],
    ]);

    expect($html)->toContain('<picture>')
        ->toContain('width="600"')
        ->toContain('height="400"')
        ->toContain('alt="A harbour at dawn"')
        ->toContain('src="'.$media->url('card').'"');

    // A <source> pointing at a format the box cannot encode would 404, and a
    // browser that picks it shows a broken image rather than falling back.
    foreach (['avif', 'webp'] as $format) {
        $supported = in_array($format, app(MediaLibrary::class)->modernFormats(), true);

        expect(str_contains($html, 'type="image/'.$format.'"'))->toBe($supported);
    }
});

it('falls back to the library alt text when the entry has none', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();
    $media->forceFill(['alt' => 'From the library'])->save();

    $html = Blade::render('<x-cms-image :value="$value" />', ['value' => ['id' => $media->uuid]]);

    expect($html)->toContain('alt="From the library"');
});

it('still renders a legacy {src, alt} reference', function (): void {
    $html = Blade::render('<x-cms-image :value="$value" />', [
        'value' => ['src' => '/images/old.jpg', 'alt' => 'Old'],
    ]);

    expect($html)->toBe('<img src="/images/old.jpg" alt="Old" loading="lazy" decoding="async">');
});

it('uses the og preset in a crawler-safe format for social images', function (): void {
    $media = Media::query()->where('uuid', uploadImage()->json('id'))->firstOrFail();

    $article = makeArticle('og-image', 'An article with a library image', [
        'seo' => ['og_image' => ['id' => $media->uuid, 'alt' => 'A harbour']],
    ]);

    $seo = app(SeoResolver::class)->forEntry($article);

    expect($seo->image)->toEndWith($media->url('og', 'jpg'))
        ->and($seo->imageAlt)->toBe('A harbour');
});
