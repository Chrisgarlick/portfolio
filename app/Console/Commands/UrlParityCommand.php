<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Cg\Cms\Redirects\NotFoundHandler;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/**
 * Every live URL must still answer. The cutover gate, plan section 7.
 *
 * Reads deploy/live-urls.txt (shipped in every release, so it can be run on the box) (the live sitemap plus every live
 * redirect) and checks each URL's status and, for redirects, where it goes.
 *
 * In-process by default, rendering through the kernel like the SEO audit, so
 * it runs with no web server and in a test. With --base it makes real HTTP
 * requests instead, which is how it is run against the box at cutover: that
 * exercises nginx, the page cache and the redirect map exactly as visitors do.
 */
final class UrlParityCommand extends Command
{
    protected $signature = 'site:url-parity
        {--base= : Check over HTTP against this origin, e.g. https://staging.chrisgarlick.com}
        {--file= : The URL list (default deploy/live-urls.txt)}';

    protected $description = 'Check that every URL the live site published still resolves as expected';

    public function handle(HttpKernel $kernel): int
    {
        $file = (string) ($this->option('file') ?: base_path('deploy/live-urls.txt'));

        if (! is_file($file)) {
            $this->components->error("No URL list at {$file}.");

            return self::FAILURE;
        }

        $base = $this->option('base') ? rtrim((string) $this->option('base'), '/') : null;
        $original = app('request');
        $failures = [];
        $checked = 0;

        try {
            foreach ($this->expectations($file) as [$path, $status, $location]) {
                $checked++;
                [$actualStatus, $actualLocation] = $base === null
                    ? $this->inProcess($kernel, $path)
                    : $this->overHttp($base, $path);

                $ok = $actualStatus === $status
                    && ($location === null || $this->pathOf($actualLocation) === $location);

                if (! $ok) {
                    $failures[] = [$path, "{$status}".($location ? " {$location}" : ''), $actualStatus.($actualLocation ? ' '.$this->pathOf($actualLocation) : '')];
                }
            }
        } finally {
            app()->instance('request', $original);
            Facade::clearResolvedInstance('request');
        }

        $this->components->twoColumnDetail('URLs checked', (string) $checked);

        if ($failures === []) {
            $this->components->info('Every live URL resolves as expected.');

            return self::SUCCESS;
        }

        $this->components->error(count($failures).' URL(s) do not match:');
        $this->table(['URL', 'Expected', 'Got'], $failures);

        return self::FAILURE;
    }

    /** @return array<int, array{0: string, 1: int, 2: string|null}> */
    private function expectations(string $file): array
    {
        $lines = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line) ?: [];
            $lines[] = [$parts[0], (int) ($parts[1] ?? 200), $parts[2] ?? null];
        }

        return $lines;
    }

    /** @return array{0: int, 1: string|null} */
    private function inProcess(HttpKernel $kernel, string $path): array
    {
        $request = Request::create(rtrim((string) config('app.url'), '/').$path, 'GET');

        // Like the SEO audit: a parity check must not count as a visit.
        $request->attributes->set(NotFoundHandler::UNCOUNTED, true);

        $response = $kernel->handle($request);

        return [$response->getStatusCode(), $response->headers->get('Location')];
    }

    /** @return array{0: int, 1: string|null} */
    private function overHttp(string $base, string $path): array
    {
        $response = Http::withoutRedirecting()->timeout(30)->get($base.$path);

        return [$response->status(), $response->header('Location') ?: null];
    }

    private function pathOf(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return (string) (parse_url($url, PHP_URL_PATH) ?: '/');
    }
}
