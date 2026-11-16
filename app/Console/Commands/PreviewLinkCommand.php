<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PreviewAccess;
use Cg\Cms\Models\Entry;
use Illuminate\Console\Command;

/**
 * Prints a client preview's link and password, to send them. `design
 * publish` runs it over SSH after uploading, so a new preview's link comes
 * straight back to the terminal.
 */
final class PreviewLinkCommand extends Command
{
    protected $signature = 'preview:link
        {slug : The preview\'s slug (its address on the preview host)}
        {--replace : Make a new link; the old one stops working}
        {--new-password : Make a new password}';

    protected $description = 'Print a client preview\'s link and password';

    public function handle(): int
    {
        $entry = Entry::query()->collection('preview')->where('slug', $this->argument('slug'))->first();
        if ($entry === null) {
            $this->error("No client preview with the slug `{$this->argument('slug')}`. Add it in the admin under Client previews.");

            return self::FAILURE;
        }

        $access = PreviewAccess::for($entry);
        if ($this->option('replace')) {
            $access->update(['token' => PreviewAccess::newToken()]);
        }
        if ($this->option('new-password')) {
            $access->update(['password' => PreviewAccess::newPassword()]);
        }

        if ($entry->status !== 'published') {
            $this->warn("{$entry->title} isn't published, so the link won't work until it is.");
        }
        $this->line("Link:     {$access->link()}");
        $this->line("Password: {$access->password}");

        return self::SUCCESS;
    }
}
