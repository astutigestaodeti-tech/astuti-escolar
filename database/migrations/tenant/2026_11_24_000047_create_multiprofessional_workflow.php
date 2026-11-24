<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('multiprofessional_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignUuid('active_search_case_id')->nullable()->unique()->constrained('active_search_cases')->restrictOnDelete();
            $table->string('area', 24);
            $table->string('reason_category', 24);
            $table->string('status', 24)->default('open');
            $table->uuid('requested_by');
            $table->timestampTz('opened_at');
            $table->uuid('assigned_to')->nullable();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->string('school_guidance', 500)->nullable();
            $table->timestampTz('guidance_at')->nullable();
            $table->string('closing_category', 24)->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->index(['school_year_id', 'opened_at']);
            $table->index(['area', 'status', 'assigned_to'], 'multiprofessional_queue_idx');
            $table->index(['enrollment_id', 'school_id']);
        });

        Schema::connection('tenant')->create('multiprofessional_attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('multiprofessional_requests')->restrictOnDelete();
            $table->date('attended_on');
            $table->uuid('recorded_by');
            $table->timestampsTz();
            $table->unique(['request_id', 'attended_on']);
            $table->index('attended_on');
        });

        Schema::connection('tenant')->create('multiprofessional_referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('multiprofessional_requests')->restrictOnDelete();
            $table->string('destination', 24);
            $table->date('referred_on');
            $table->date('returned_on')->nullable();
            $table->string('return_category', 24)->nullable();
            $table->uuid('recorded_by');
            $table->timestampsTz();
            $table->index('referred_on');
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE multiprofessional_requests ADD CONSTRAINT multiprofessional_area_chk CHECK (area IN ('psychology', 'social_work', 'pedagogy', 'undetermined'))");
        $db->statement("ALTER TABLE multiprofessional_requests ADD CONSTRAINT multiprofessional_reason_chk CHECK (reason_category IN ('learning', 'behavior', 'attendance', 'family_social', 'inclusion', 'other'))");
        $db->statement("ALTER TABLE multiprofessional_requests ADD CONSTRAINT multiprofessional_status_chk CHECK (status IN ('open', 'scheduled', 'attending', 'referred', 'closed'))");
        $db->statement("ALTER TABLE multiprofessional_requests ADD CONSTRAINT multiprofessional_closing_chk CHECK ((status = 'closed' AND closed_at IS NOT NULL AND closing_category IS NOT NULL AND closing_category IN ('completed', 'network_followup', 'left_school', 'no_contact', 'other')) OR (status <> 'closed' AND closed_at IS NULL AND closing_category IS NULL))");
        $db->statement("ALTER TABLE multiprofessional_requests ADD CONSTRAINT multiprofessional_assignment_chk CHECK ((assigned_to IS NULL AND assigned_at IS NULL AND status = 'open' AND scheduled_at IS NULL AND school_guidance IS NULL) OR (assigned_to IS NOT NULL AND assigned_at IS NOT NULL AND area <> 'undetermined'))");
        $db->statement("ALTER TABLE multiprofessional_requests ADD CONSTRAINT multiprofessional_schedule_chk CHECK (status <> 'scheduled' OR scheduled_at IS NOT NULL)");
        $db->statement('ALTER TABLE multiprofessional_requests ADD CONSTRAINT multiprofessional_guidance_chk CHECK ((school_guidance IS NULL AND guidance_at IS NULL) OR (school_guidance IS NOT NULL AND guidance_at IS NOT NULL))');
        $db->statement("CREATE UNIQUE INDEX multiprofessional_open_area_unique ON multiprofessional_requests (enrollment_id, area) WHERE status <> 'closed'");
        $db->statement("ALTER TABLE multiprofessional_referrals ADD CONSTRAINT multiprofessional_destination_chk CHECK (destination IN ('cras', 'creas', 'capsi', 'ubs', 'conselho_tutelar', 'apae', 'ministerio_publico', 'defensoria', 'other'))");
        $db->statement("ALTER TABLE multiprofessional_referrals ADD CONSTRAINT multiprofessional_return_chk CHECK ((returned_on IS NULL AND return_category IS NULL) OR (returned_on IS NOT NULL AND returned_on >= referred_on AND return_category IS NOT NULL AND return_category IN ('received', 'in_progress', 'not_admitted')))");
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('multiprofessional_referrals');
        Schema::connection('tenant')->dropIfExists('multiprofessional_attendances');
        Schema::connection('tenant')->dropIfExists('multiprofessional_requests');
    }
};
