<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Admin\QueueMonitor;
use Cg\Cms\Jobs\RunSeoAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The queue screen
|------------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['queue.default' => 'database']);
    Cache::forget(QueueMonitor::HEARTBEAT_KEY);
    Cache::forget(QueueMonitor::HEARTBEAT_KEY.':fresh');
    $this->actingAs(User::factory()->create());
});

function queueScreen(): array
{
    return test()->get('/admin/queue', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ])->assertOk()->json('props');
}

function failJob(string $class = 'App\\Jobs\\RunSiteAudit'): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['uuid' => $uuid, 'displayName' => $class, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => ['commandName' => $class, 'command' => 'O:8:"stdClass":0:{}']]),
        'exception' => "RuntimeException: The audit timed out\n#0 /app/Jobs/RunSiteAudit.php(42)",
        'failed_at' => now(),
    ]);

    return $uuid;
}

it('lists waiting jobs by a name a person would use, and flags a missing worker', function (): void {
    RunSeoAudit::dispatch();

    $props = queueScreen();

    expect($props['pending'])->toHaveCount(1)
        ->and($props['pending'][0])->toMatchArray(['name' => 'SEO audit', 'state' => 'waiting'])
        ->and($props['worker']['running'])->toBeFalse()
        ->and($props['nav']['queue'])->toBe(['pending' => 1, 'failed' => 0, 'stalled' => true]);
});

it('knows a worker is running from its heartbeat', function (): void {
    RunSeoAudit::dispatch();
    app(QueueMonitor::class)->beat();

    $props = queueScreen();

    expect($props['worker']['running'])->toBeTrue()
        ->and($props['nav']['queue']['stalled'])->toBeFalse();
});

it('shows failed jobs with their error, and retries or removes them', function (): void {
    $uuid = failJob();

    $failed = queueScreen()['failed'];

    expect($failed[0])->toMatchArray(['id' => $uuid, 'name' => 'Site audit for a lead', 'error' => 'RuntimeException: The audit timed out']);

    $this->post("/admin/queue/failed/{$uuid}/retry")->assertRedirect();

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(1);

    $other = failJob();
    $this->delete("/admin/queue/failed/{$other}")->assertRedirect();

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('cancels a waiting job but never one that is running', function (): void {
    RunSeoAudit::dispatch();
    $id = (int) DB::table('jobs')->value('id');

    DB::table('jobs')->where('id', $id)->update(['reserved_at' => time()]);
    $this->delete("/admin/queue/jobs/{$id}")->assertSessionHas('error');
    expect(DB::table('jobs')->count())->toBe(1);

    DB::table('jobs')->where('id', $id)->update(['reserved_at' => null]);
    $this->delete("/admin/queue/jobs/{$id}")->assertSessionHas('success');
    expect(DB::table('jobs')->count())->toBe(0);
});

it('says so instead of guessing when the queue is not the database driver', function (): void {
    config(['queue.default' => 'sync']);

    expect(queueScreen())->toMatchArray(['supported' => false, 'pending' => [], 'failed' => []]);
});
