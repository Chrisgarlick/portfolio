<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The media library's disk is always a throwaway in tests: running the
        // content installer (or any upload) would otherwise store a real copy
        // of every image in storage/ on every run. A test that fakes the disk
        // itself just gets a fresh fake.
        Storage::fake((string) config('cg-cms.media.disk', 'local'));
    }

    protected function tearDown(): void
    {
        // Image variants rendered during a test land in public/media-test
        // (phpunit.xml's media path): clear them, or every run leaves copies.
        File::deleteDirectory(public_path('media-test'));

        parent::tearDown();
    }
}
