<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Client previews: the access to each `preview` entry, and its views
|------------------------------------------------------------------------------
|
| The token in the client's link and their password are encrypted with the
| app key, not hashed: the studio shows them again whenever a client loses
| the link. They only ever open images of a design.
|
| Views record when and which page, and nothing about who: no IP, no agent.
| Enough to see whether a client has looked.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->unique()->constrained('entries')->cascadeOnDelete();
            $table->text('token');
            $table->text('password');
            $table->timestampTz('last_viewed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('preview_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->string('page')->nullable();
            $table->timestampTz('viewed_at')->useCurrent();

            $table->index(['entry_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_views');
        Schema::dropIfExists('preview_access');
    }
};
