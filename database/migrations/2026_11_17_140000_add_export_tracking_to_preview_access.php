<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Client previews: publishing through Design's background exports
|------------------------------------------------------------------------------
|
| A publish starts an export job in Design and checks on it: the job's id, how
| far it has got ("7 / 16"), and when the publish started, so a stuck one can
| be given up on.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preview_access', function (Blueprint $table): void {
            $table->string('export_id')->nullable();
            $table->string('publish_progress')->nullable();
            $table->timestampTz('publish_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('preview_access', function (Blueprint $table): void {
            $table->dropColumn(['export_id', 'publish_progress', 'publish_started_at']);
        });
    }
};
