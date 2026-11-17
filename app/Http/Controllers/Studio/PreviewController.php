<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Jobs\PublishPreview;
use App\Models\PreviewAccess;
use App\Models\PreviewView;
use Carbon\CarbonImmutable;
use Cg\Cms\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Client previews: the link and password to send each client, whether they
 * have looked, and new ones when a link has gone somewhere it shouldn't.
 *
 * The previews themselves (who, the address, the expiry, on or off) are
 * `preview` entries, edited in the CMS admin like any other content.
 */
final class PreviewController extends Controller
{
    public function index(): View
    {
        $previews = Entry::query()->collection('preview')->orderBy('title')->get();
        $views = PreviewView::query()
            ->whereIn('entry_id', $previews->pluck('id'))
            ->selectRaw('entry_id, count(*) as views')
            ->groupBy('entry_id')
            ->pluck('views', 'entry_id');

        return view('studio.previews.index', [
            'previews' => $previews->map(fn (Entry $entry): array => [
                'entry' => $entry,
                'access' => PreviewAccess::for($entry)->setRelation('entry', $entry),
                'views' => (int) ($views[$entry->id] ?? 0),
                'expires_at' => filled($entry->value('expires_at')) ? CarbonImmutable::parse($entry->value('expires_at')) : null,
            ]),
        ]);
    }

    /** A new token: the old link stops working. */
    public function newLink(Entry $entry): RedirectResponse
    {
        $this->preview($entry)->update(['token' => PreviewAccess::newToken()]);

        return back()->with('success', "New link for {$entry->title}. The old one no longer works.");
    }

    public function newPassword(Entry $entry): RedirectResponse
    {
        $this->preview($entry)->update(['password' => PreviewAccess::newPassword()]);

        return back()->with('success', "New password for {$entry->title}.");
    }

    /**
     * Fetch the preview's Design file and put it live, on the queue (a big
     * bundle takes a minute or two). The page shows how it went.
     */
    public function publish(Entry $entry): RedirectResponse
    {
        $access = $this->preview($entry);
        if (blank($entry->value('design_file'))) {
            return back()->with('error', "{$entry->title} has no Design file yet: add its id in the admin.");
        }
        if (blank(config('site.previews.design_token'))) {
            return back()->with('error', 'Publishing needs DESIGN_PREVIEW_TOKEN on the server (an export-only Design token).');
        }
        $access->update(['publish_status' => 'queued', 'publish_error' => null]);
        PublishPreview::dispatch($entry->id);

        return back()->with('success', "Publishing {$entry->title} from Design. It shows here as it goes (you can also watch or stop it in Design's Exports panel); the link stays the same.");
    }

    private function preview(Entry $entry): PreviewAccess
    {
        if ($entry->collection !== 'preview') {
            throw new NotFoundHttpException;
        }

        return PreviewAccess::for($entry);
    }
}
