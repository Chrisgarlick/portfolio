<?php

declare(strict_types=1);

/*
| env() returns null once the config is cached, which production always does.
| Called anywhere but config/, it works in development and silently fails live:
| the contact form once lost its notification address exactly this way.
*/

it('calls env() only from the config folder', function (): void {
    $offenders = [];

    foreach (['app', 'routes', 'resources/views', 'bootstrap'] as $folder) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($folder), FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && preg_match('/(?<![\w>:$])env\(/', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('sends form notifications to the configured contact address', function (): void {
    expect(config('cg-forms.contact.notify'))->toBe(config('cg-cms.site.contact_email'))
        ->and(config('cg-forms.enquiry.notify'))->toBe(config('cg-cms.site.contact_email'));
});
