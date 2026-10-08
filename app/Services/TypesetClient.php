<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Markdown or a JSON layout in, PDF or DOCX out, via typeset.chrisgarlick.com.
 *
 * Ported from renderViaTypeset() in server.ts, including its cache: output is
 * kept on disk under a hash of everything that affects it (the content, the
 * format, the input format and the client theme), so a document renders once
 * per change however many people download it. A changed resource gets a new
 * hash and therefore a fresh render; nothing needs purging.
 *
 * The cache lives on the local disk under storage/, never public/, because
 * gated downloads must go through the controller that checks the lead.
 */
final class TypesetClient
{
    public const MIME = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const CACHE_DIR = 'typeset-cache';

    public function configured(): bool
    {
        return filled(config('services.typeset.key'));
    }

    /**
     * The rendered document's bytes, from the cache when possible.
     *
     * @param  string  $name  A slug, used only to make cache files readable.
     * @param  string  $inputFormat  markdown | json
     * @param  string|null  $client  Typeset's per-client theme.
     *
     * @throws TypesetException
     */
    public function render(string $name, string $content, string $format, string $inputFormat = 'markdown', ?string $client = null): string
    {
        if (! isset(self::MIME[$format])) {
            throw new TypesetException("Unsupported format [{$format}].", 400);
        }

        if (trim($content) === '') {
            throw new TypesetException('Resource content not found.', 404);
        }

        $path = $this->cachePath($name, $content, $format, $inputFormat, $client);
        $disk = Storage::disk('local');

        if ($disk->exists($path)) {
            return (string) $disk->get($path);
        }

        if (! $this->configured()) {
            throw new TypesetException('PDF/DOCX rendering is not configured.', 503);
        }

        $body = array_filter([
            'document_type' => 'general',
            'format' => $format,
            'input_format' => $inputFormat,
            'content' => $content,
            'client' => $client,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::withToken((string) config('services.typeset.key'))
                ->acceptJson()
                ->timeout((int) config('services.typeset.timeout', 60))
                ->post(rtrim((string) config('services.typeset.url'), '/').'/api/render', $body);
        } catch (ConnectionException $e) {
            Log::error('Typeset unreachable', ['error' => $e->getMessage()]);

            throw new TypesetException('Could not reach the rendering service.');
        }

        if (! $response->successful()) {
            Log::error('Typeset render failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

            throw new TypesetException('Rendering failed. Please try again shortly.');
        }

        $bytes = $response->body();
        $disk->put($path, $bytes);

        return $bytes;
    }

    /** Whether a render is already cached, so a caller can skip queueing one. */
    public function isCached(string $name, string $content, string $format, string $inputFormat = 'markdown', ?string $client = null): bool
    {
        return Storage::disk('local')->exists($this->cachePath($name, $content, $format, $inputFormat, $client));
    }

    private function cachePath(string $name, string $content, string $format, string $inputFormat, ?string $client): string
    {
        $hash = substr(hash('sha256', implode('|', [$name, $format, $inputFormat, (string) $client, $content])), 0, 16);

        return self::CACHE_DIR.'/'.preg_replace('/[^a-z0-9-]+/i', '-', $name)."-{$format}-{$hash}.{$format}";
    }
}
