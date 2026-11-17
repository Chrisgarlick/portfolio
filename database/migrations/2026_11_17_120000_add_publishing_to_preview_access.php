<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Client previews: publishing from the studio
|------------------------------------------------------------------------------
|
| Where the last "Publish from Design" got to, for the studio to show: queued,
| publishing, published (with when and which version) or failed (with why).
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preview_access', function (Blueprint $table): void {
            $table->string('publish_status')->nullable();
            $table->text('publish_error')->nullable();
            $table->string('published_version')->nullable();
            $table->timestampTz('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('preview_access', function (Blueprint $table): void {
            $table->dropColumn(['publish_status', 'publish_error', 'published_version', 'published_at']);
        });
    }
};
