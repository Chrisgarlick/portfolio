<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
| cms:user: admin accounts from the command line. Passwords come from hidden
| input or a .env key, never an argument.
*/

function envWith(string $line): string
{
    $path = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, "APP_NAME=test\n{$line}\n");
    app()->loadEnvironmentFrom(basename($path));
    app()->useEnvironmentPath(dirname($path));

    return $path;
}

it('creates an account with the password read from .env', function (): void {
    envWith('CMS_ADMIN_PASSWORD="a long enough password"');

    $this->artisan('cms:user', ['email' => 'Owner@Example.com', '--password-env' => 'CMS_ADMIN_PASSWORD'])
        ->expectsOutputToContain('Now remove CMS_ADMIN_PASSWORD from .env')
        ->assertSuccessful();

    $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
    expect(Hash::check('a long enough password', $user->password))->toBeTrue()
        ->and($user->name)->toBe('Owner');
});

it('asks twice with hidden input when there is no --password-env', function (): void {
    $this->artisan('cms:user', ['email' => 'owner@example.com'])
        ->expectsQuestion('Password (at least 12 characters)', 'correct horse battery')
        ->expectsQuestion('Same again', 'correct horse battery')
        ->assertSuccessful();

    expect(Hash::check('correct horse battery', User::query()->where('email', 'owner@example.com')->value('password')))->toBeTrue();
});

it('changes nothing for a short, mismatched or missing password', function (): void {
    envWith('SHORT=tiny');

    $this->artisan('cms:user', ['email' => 'a@example.com', '--password-env' => 'SHORT'])->assertFailed();
    $this->artisan('cms:user', ['email' => 'a@example.com', '--password-env' => 'MISSING'])->assertFailed();
    $this->artisan('cms:user', ['email' => 'a@example.com'])
        ->expectsQuestion('Password (at least 12 characters)', 'correct horse battery')
        ->expectsQuestion('Same again', 'something else entirely')
        ->assertFailed();

    expect(User::query()->where('email', 'a@example.com')->exists())->toBeFalse();
});

it('removes an account, crediting its edits to another', function (): void {
    $old = User::factory()->create(['email' => 'old@example.com']);
    $new = User::factory()->create(['email' => 'new@example.com']);
    $entry = makeArticle('by-old');
    $entry->forceFill(['created_by' => $old->id, 'updated_by' => $old->id])->saveQuietly();

    $this->artisan('cms:user', ['email' => 'old@example.com', '--delete' => true, '--transfer-to' => 'new@example.com', '--force' => true])
        ->assertSuccessful();

    $entry = Entry::query()->findOrFail($entry->id);
    expect(User::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and($entry->created_by)->toBe($new->id)
        ->and($entry->updated_by)->toBe($new->id);
});

it('never removes the last account', function (): void {
    User::factory()->create(['email' => 'only@example.com']);

    $this->artisan('cms:user', ['email' => 'only@example.com', '--delete' => true, '--force' => true])
        ->expectsOutputToContain('only account')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});
