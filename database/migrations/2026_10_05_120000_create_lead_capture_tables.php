<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Lead capture: the tables server.ts created at boot on the live site
|------------------------------------------------------------------------------
|
| Ported as they are, column for column, so the Phase 6 import is a copy and
| not a transformation. Additions are marked. The live site created these with
| CREATE TABLE IF NOT EXISTS on every boot, which is why they never had a
| migration before.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Free site-audit tool runs. Table name kept from the live site.
         *
         * Added: status, error and completed_at. The live endpoint held the
         * request open for up to 60 seconds while it polled Kritano; here the
         * audit runs on the queue and the page polls this row instead, so a
         * PHP worker is never parked waiting on somebody else's API.
         */
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('url');
            $table->text('domain')->nullable();
            $table->text('ip')->nullable();
            $table->jsonb('scores')->nullable();
            $table->jsonb('issues')->nullable();
            $table->text('kritano_audit_id')->nullable();
            $table->text('task')->nullable();
            $table->string('status')->default('queued');
            $table->text('error')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('created_at');
        });

        // Gated-resource leads, one per email. Consent is never downgraded.
        Schema::create('resource_leads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('email')->unique();
            $table->text('first_name')->nullable();
            $table->text('company')->nullable();
            $table->text('sector')->nullable();
            $table->text('source_slug')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->text('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('resource_downloads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('lead_id')->constrained('resource_leads')->cascadeOnDelete();
            $table->text('resource_slug');
            $table->text('format');
            $table->text('ip')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('resource_slug');
        });

        // AI readiness audit requests from /audit, and their review workflow.
        Schema::create('audit_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('audit_ref')->unique();
            $table->text('email');
            $table->jsonb('data');
            $table->text('status')->default('submitted');
            $table->text('pdf_path')->nullable();
            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('privacy_notice_version');
            $table->text('admin_notes')->nullable();
            $table->text('audit_markdown')->nullable();
            $table->timestampTz('submitted_at')->useCurrent();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->text('deletion_reason')->nullable();

            $table->index('email');
            $table->index('status');
            $table->index('submitted_at');
        });

        // Every transactional email sent, for subject access requests.
        Schema::create('outbound_email_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('audit_submission_id')->nullable()->constrained('audit_submissions')->nullOnDelete();
            $table->text('to_email');
            $table->text('subject');
            $table->text('template');
            $table->timestampTz('sent_at')->useCurrent();
            $table->text('resend_message_id')->nullable();

            $table->index('to_email');
        });

        /*
         * Added: the erasure audit trail.
         *
         * The runbook told whoever ran a deletion to paste the metadata into
         * storage/gdpr-deletions.log by hand. A table cannot be forgotten, and
         * it holds a hash of the email rather than the email, so the record of
         * a deletion is not itself personal data.
         */
        Schema::create('gdpr_deletions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('email_hash', 64);
            $table->text('audit_ref')->nullable();
            $table->text('reason');
            $table->jsonb('counts');
            $table->timestampTz('deleted_at')->useCurrent();

            $table->index('email_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_deletions');
        Schema::dropIfExists('outbound_email_log');
        Schema::dropIfExists('audit_submissions');
        Schema::dropIfExists('resource_downloads');
        Schema::dropIfExists('resource_leads');
        Schema::dropIfExists('audit_logs');
    }
};
