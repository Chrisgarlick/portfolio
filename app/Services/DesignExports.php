<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Design's background exports, with the export-only token
 * (DESIGN_PREVIEW_TOKEN): start one, ask how it's going, download it.
 *
 *   POST /v1/files/{file}/exports  {format: "preview", frames: [...]}  → {id, status}
 *   GET  /v1/exports/{id}          → {status, progress_done, progress_total, error, ...}
 *   GET  /v1/exports/{id}/download → the zip, once done
 */
final class DesignExports
{
    public function configured(): bool
    {
        return filled(config('site.previews.design_token'));
    }

    /** Starts a preview export and returns its id. */
    public function start(string $file, array $frames): string
    {
        $body = ['format' => 'preview'] + ($frames === [] ? [] : ['frames' => $frames]);
        $response = $this->http()->timeout(60)->post("/v1/files/{$file}/exports", $body);
        $this->ok($response);
        $id = (string) $response->json('id');
        if ($id === '') {
            throw new RuntimeException('Design didn\'t say which export it started.');
        }

        return $id;
    }

    /** The export's state: status, progress_done, progress_total, error. */
    public function status(string $id): array
    {
        $response = $this->http()->timeout(30)->get("/v1/exports/{$id}");
        $this->ok($response);

        return (array) $response->json();
    }

    /** Downloads a finished export to $path. */
    public function download(string $id, string $path): void
    {
        $this->ok($this->http()->timeout(600)->sink($path)->get("/v1/exports/{$id}/download"), $path);
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('site.previews.design_token'))
            ->baseUrl(rtrim((string) config('site.previews.design_url'), '/'))
            ->acceptJson();
    }

    private function ok(Response $response, ?string $sink = null): void
    {
        if ($response->successful()) {
            return;
        }
        $body = $sink !== null ? json_decode((string) @file_get_contents($sink), true) : $response->json();
        $detail = is_array($body) ? ($body['detail'] ?? $body['message'] ?? '') : '';
        throw new RuntimeException(trim("Design answered {$response->status()}. {$detail}"));
    }
}
