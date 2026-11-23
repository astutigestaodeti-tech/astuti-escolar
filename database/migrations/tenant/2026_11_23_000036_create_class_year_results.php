<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('class_year_closures', function (Blueprint $table): void {
            $table->foreignUuid('school_class_id')->primary()->constrained('school_classes')->restrictOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->string('close_reason', 500)->nullable();
            $table->timestampTz('reopened_at')->nullable();
            $table->uuid('reopened_by')->nullable();
            $table->string('reopen_reason', 500)->nullable();
            $table->timestampsTz();
        });

        Schema::connection('tenant')->create('student_final_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignUuid('student_enrollment_id')->unique()->constrained('student_enrollments')->restrictOnDelete();
            $table->string('suggested_result', 12);
            $table->string('result', 12);
            $table->string('override_reason', 500)->nullable();
            $table->uuid('confirmed_by');
            $table->timestampTz('confirmed_at');
            $table->timestampsTz();
            $table->index(['school_class_id', 'result'], 'student_final_results_class_status_idx');
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE student_final_results ADD CONSTRAINT student_final_results_choice_check CHECK (result IN ('aprovado', 'retido', 'cursando') AND suggested_result IN ('aprovado', 'retido', 'cursando'))");
        $db->statement("ALTER TABLE student_final_results ADD CONSTRAINT student_final_results_reason_check CHECK ((result = suggested_result AND override_reason IS NULL) OR (result <> suggested_result AND length(btrim(override_reason)) >= 10))");
        $db->statement('ALTER TABLE class_year_closures ADD CONSTRAINT class_year_closures_close_check CHECK ((closed_at IS NULL AND closed_by IS NULL AND close_reason IS NULL) OR (closed_at IS NOT NULL AND closed_by IS NOT NULL AND close_reason IS NOT NULL))');
        $db->statement('ALTER TABLE class_year_closures ADD CONSTRAINT class_year_closures_reopen_check CHECK ((reopened_at IS NULL AND reopened_by IS NULL AND reopen_reason IS NULL) OR (reopened_at IS NOT NULL AND reopened_by IS NOT NULL AND reopen_reason IS NOT NULL))');

        // Linhas permanentes para SERIALIZAR a gravação de notas com o fechamento.
        // Nenhuma inserção de nota ganha corrida contra a confirmação do encerramento.
        $db->statement(<<<'SQL'
INSERT INTO class_year_closures (school_class_id, created_at, updated_at)
SELECT id, now(), now() FROM school_classes
SQL);
        $db->unprepared(<<<'SQL'
CREATE FUNCTION ensure_class_year_closure() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO class_year_closures (school_class_id, created_at, updated_at)
    VALUES (NEW.id, now(), now());
    RETURN NEW;
END;
$$;
CREATE TRIGGER school_classes_create_year_closure
AFTER INSERT ON school_classes FOR EACH ROW EXECUTE FUNCTION ensure_class_year_closure();

CREATE FUNCTION guard_finalized_class() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    target_class uuid;
    is_closed timestamptz;
BEGIN
    target_class := CASE WHEN TG_OP = 'DELETE' THEN OLD.school_class_id ELSE NEW.school_class_id END;
    SELECT closed_at INTO is_closed
    FROM class_year_closures
    WHERE school_class_id = target_class FOR SHARE;
    IF is_closed IS NOT NULL THEN
        RAISE EXCEPTION 'Ano letivo da turma fechado. Reabertura da gestão municipal necessária.' USING ERRCODE = '23514';
    END IF;
    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$;
CREATE TRIGGER grade_entries_closed_class_guard
BEFORE INSERT OR UPDATE OR DELETE ON grade_entries
FOR EACH ROW EXECUTE FUNCTION guard_finalized_class();
CREATE TRIGGER student_final_results_closed_class_guard
BEFORE INSERT OR UPDATE OR DELETE ON student_final_results
FOR EACH ROW EXECUTE FUNCTION guard_finalized_class();
SQL);
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        $db->statement('DROP TRIGGER IF EXISTS student_final_results_closed_class_guard ON student_final_results');
        $db->statement('DROP TRIGGER IF EXISTS grade_entries_closed_class_guard ON grade_entries');
        $db->statement('DROP TRIGGER IF EXISTS school_classes_create_year_closure ON school_classes');
        $db->statement('DROP FUNCTION IF EXISTS guard_finalized_class()');
        $db->statement('DROP FUNCTION IF EXISTS ensure_class_year_closure()');
        Schema::connection('tenant')->dropIfExists('student_final_results');
        Schema::connection('tenant')->dropIfExists('class_year_closures');
    }
};
