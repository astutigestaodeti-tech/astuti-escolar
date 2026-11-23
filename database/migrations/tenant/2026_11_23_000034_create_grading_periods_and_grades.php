<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('academic_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->string('kind', 12);
            $table->unsignedTinyInteger('number');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampTz('closed_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->string('close_reason', 500)->nullable();
            $table->timestampsTz();
            $table->unique(['school_year_id', 'number'], 'academic_periods_year_number_unique');
            $table->index(['school_year_id', 'closed_at'], 'academic_periods_year_closed_idx');
        });

        Schema::connection('tenant')->create('curricular_components', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->boolean('is_optional')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(100);
            $table->timestampsTz();
        });

        Schema::connection('tenant')->create('grade_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignUuid('academic_period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->foreignUuid('curricular_component_id')->constrained('curricular_components')->restrictOnDelete();
            $table->decimal('grade', 3, 1)->nullable();
            $table->unsignedSmallInteger('absences')->nullable();
            $table->uuid('recorded_by');
            $table->uuid('updated_by');
            $table->timestampsTz();
            $table->unique(
                ['student_enrollment_id', 'academic_period_id', 'curricular_component_id'],
                'grade_entries_enrollment_period_component_unique',
            );
            $table->index(['school_class_id', 'academic_period_id', 'curricular_component_id'], 'grade_entries_class_period_component_idx');
        });

        $db = DB::connection('tenant');
        $db->statement("ALTER TABLE academic_periods ADD CONSTRAINT academic_periods_kind_check CHECK (kind IN ('bimestre', 'trimestre'))");
        $db->statement('ALTER TABLE academic_periods ADD CONSTRAINT academic_periods_number_check CHECK ((kind = \'bimestre\' AND number BETWEEN 1 AND 4) OR (kind = \'trimestre\' AND number BETWEEN 1 AND 3))');
        $db->statement('ALTER TABLE academic_periods ADD CONSTRAINT academic_periods_dates_check CHECK (starts_on <= ends_on)');
        $db->statement('ALTER TABLE academic_periods ADD CONSTRAINT academic_periods_close_check CHECK ((closed_at IS NULL AND closed_by IS NULL AND close_reason IS NULL) OR (closed_at IS NOT NULL AND closed_by IS NOT NULL AND close_reason IS NOT NULL))');
        $db->statement('ALTER TABLE grade_entries ADD CONSTRAINT grade_entries_value_check CHECK (grade IS NULL OR (grade >= 0 AND grade <= 10))');

        // Novos anos letivos recebem quatro períodos por padrão. Os limites
        // cobrem o intervalo cadastrado sem sobreposição; gestão pode editar.
        $db->unprepared(<<<'SQL'
CREATE FUNCTION academic_default_periods() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF (NEW.end_date - NEW.start_date + 1) < 4 THEN
        RETURN NEW;
    END IF;
    INSERT INTO academic_periods (
        id, school_year_id, kind, number, starts_on, ends_on, created_at, updated_at
    )
    SELECT gen_random_uuid(), NEW.id, 'bimestre', n,
        NEW.start_date + ((NEW.end_date - NEW.start_date + 1) * (n - 1)) / 4,
        NEW.start_date + ((NEW.end_date - NEW.start_date + 1) * n) / 4 - 1,
        now(), now()
    FROM generate_series(1, 4) AS n;
    RETURN NEW;
END;
$$;
CREATE TRIGGER school_years_academic_periods_after_insert
AFTER INSERT ON school_years FOR EACH ROW EXECUTE FUNCTION academic_default_periods();
SQL);
        $db->statement(<<<'SQL'
INSERT INTO academic_periods (id, school_year_id, kind, number, starts_on, ends_on, created_at, updated_at)
SELECT gen_random_uuid(), y.id, 'bimestre', n,
    y.start_date + ((y.end_date - y.start_date + 1) * (n - 1)) / 4,
    y.start_date + ((y.end_date - y.start_date + 1) * n) / 4 - 1,
    now(), now()
FROM school_years y CROSS JOIN generate_series(1, 4) AS n
WHERE (y.end_date - y.start_date + 1) >= 4
SQL);

        $defaults = [
            ['lingua-portuguesa', 'Língua Portuguesa', false],
            ['matematica', 'Matemática', false],
            ['ciencias', 'Ciências', false],
            ['historia', 'História', false],
            ['geografia', 'Geografia', false],
            ['arte', 'Arte', false],
            ['educacao-fisica', 'Educação Física', false],
            ['lingua-inglesa', 'Língua Inglesa', true],
        ];

        foreach ($defaults as $position => [$code, $name, $optional]) {
            $db->table('curricular_components')->insert([
                'id' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'is_optional' => $optional,
                'active' => true,
                'display_order' => ($position + 1) * 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tenant')->statement('DROP TRIGGER IF EXISTS school_years_academic_periods_after_insert ON school_years');
        DB::connection('tenant')->statement('DROP FUNCTION IF EXISTS academic_default_periods()');
        Schema::connection('tenant')->dropIfExists('grade_entries');
        Schema::connection('tenant')->dropIfExists('curricular_components');
        Schema::connection('tenant')->dropIfExists('academic_periods');
    }
};
