<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('waitlist_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('protocol', 10)->unique();
            $table->string('child_name', 180);
            $table->date('birth_date');
            $table->string('cpf', 14)->nullable();
            $table->string('guardian_name', 180);
            $table->string('guardian_phone', 11);
            $table->string('postal_code', 9);
            $table->string('address_street', 180);
            $table->string('address_number', 20);
            $table->string('address_complement', 180)->nullable();
            $table->string('address_district', 120);
            $table->string('address_city', 120);
            $table->string('address_state', 2);
            $table->unsignedSmallInteger('intake_year');
            $table->date('cutoff_date');
            $table->string('stage', 20);
            $table->string('desired_shift', 20);
            // Chaves = critérios; valores = justificativas protegidas, nunca expostas em listas.
            $table->jsonb('priority_criteria');
            $table->unsignedSmallInteger('priority_count')->default(0);
            $table->string('status', 16)->default('aguardando');
            $table->timestampTz('requested_at')->useCurrent();
            $table->uuid('created_by');
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->string('deletion_reason', 500)->nullable();
            $table->timestampsTz();

            $table->index(['intake_year', 'stage', 'status', 'priority_count', 'requested_at'], 'waitlist_requests_queue_idx');
            $table->index(['child_name', 'birth_date'], 'waitlist_requests_child_birth_idx');
            $table->index('cpf', 'waitlist_requests_cpf_idx');
        });

        Schema::connection('tenant')->create('waitlist_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('waitlist_request_id')->constrained('waitlist_requests')->restrictOnDelete();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->unsignedSmallInteger('preference_order');
            $table->unique(['waitlist_request_id', 'school_id'], 'waitlist_preferences_distinct_school');
            $table->unique(['waitlist_request_id', 'preference_order'], 'waitlist_preferences_distinct_position');
            $table->index('school_id');
        });

        Schema::connection('tenant')->create('waitlist_status_changes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('waitlist_request_id')->constrained('waitlist_requests')->restrictOnDelete();
            $table->string('previous_status', 16);
            $table->string('new_status', 16);
            $table->string('reason', 500);
            $table->uuid('changed_by');
            $table->timestampTz('changed_at')->useCurrent();
            $table->index(['waitlist_request_id', 'changed_at'], 'waitlist_status_changes_history_idx');
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE waitlist_requests ADD CONSTRAINT waitlist_requests_cpf_chk CHECK (cpf IS NULL OR cpf ~ '^[0-9]{3}\\.[0-9]{3}\\.[0-9]{3}-[0-9]{2}$')");
        $db->statement("ALTER TABLE waitlist_requests ADD CONSTRAINT waitlist_requests_phone_chk CHECK (guardian_phone ~ '^[0-9]{10,11}$')");
        $db->statement("ALTER TABLE waitlist_requests ADD CONSTRAINT waitlist_requests_status_chk CHECK (status IN ('aguardando', 'convocado', 'desistiu', 'atendido'))");
        $db->statement("ALTER TABLE waitlist_requests ADD CONSTRAINT waitlist_requests_stage_chk CHECK (stage IN ('bercario', 'maternal_1', 'maternal_2', 'pre_1', 'pre_2'))");
        $db->statement('ALTER TABLE waitlist_requests ADD CONSTRAINT waitlist_requests_priority_chk CHECK (priority_count BETWEEN 0 AND 4)');
        $db->statement('ALTER TABLE waitlist_preferences ADD CONSTRAINT waitlist_preferences_order_chk CHECK (preference_order BETWEEN 1 AND 3)');
        $db->statement('ALTER TABLE waitlist_requests ADD CONSTRAINT waitlist_requests_deletion_chk CHECK ((deleted_at IS NULL AND deleted_by IS NULL AND deletion_reason IS NULL) OR (deleted_at IS NOT NULL AND deleted_by IS NOT NULL AND deletion_reason IS NOT NULL))');
        // Protocolo e horário são gerados pelo servidor e permanecem imutáveis,
        // inclusive se alguém tentar alterá-los diretamente por SQL.
        $db->unprepared(<<<'SQL'
CREATE FUNCTION waitlist_preserve_registration() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.requested_at IS DISTINCT FROM OLD.requested_at
       OR NEW.protocol IS DISTINCT FROM OLD.protocol
       OR NEW.intake_year IS DISTINCT FROM OLD.intake_year
       OR NEW.stage IS DISTINCT FROM OLD.stage
       OR NEW.priority_count IS DISTINCT FROM OLD.priority_count
       OR NEW.priority_criteria IS DISTINCT FROM OLD.priority_criteria THEN
        RAISE EXCEPTION 'Protocolo, data, etapa e critérios originais são imutáveis';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER waitlist_registration_immutable BEFORE UPDATE ON waitlist_requests
FOR EACH ROW EXECUTE FUNCTION waitlist_preserve_registration();
SQL);
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('waitlist_status_changes');
        Schema::connection('tenant')->dropIfExists('waitlist_preferences');
        Schema::connection('tenant')->dropIfExists('waitlist_requests');
        DB::connection('tenant')->statement('DROP FUNCTION IF EXISTS waitlist_preserve_registration()');
    }
};
