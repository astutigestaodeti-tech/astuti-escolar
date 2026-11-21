<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditTrail;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\TwoMunicipalityTestCase;

class PostgresImmutableAuditTest extends TwoMunicipalityTestCase
{
    public function test_sql_bruto_nao_altera_ou_remove_auditoria_central(): void
    {
        app(AuditTrail::class)->record('platform', 'test.pgsql.immutable', SchoolYear::class);
        $registro = DB::connection('platform')->table('platform_audit_logs')
            ->where('action', 'test.pgsql.immutable')->first();

        $this->assertNotNull($registro);

        $this->assertBloqueado(fn () => DB::connection('platform')->table('platform_audit_logs')
            ->where('id', $registro->id)->update(['action' => 'adulterada']));
        $this->assertBloqueado(fn () => DB::connection('platform')->table('platform_audit_logs')
            ->where('id', $registro->id)->delete());
        $this->assertBloqueado(fn () => DB::connection('platform')->table('platform_audit_logs')->truncate());

        $this->assertSame('test.pgsql.immutable', DB::connection('platform')
            ->table('platform_audit_logs')->where('id', $registro->id)->value('action'));
    }

    public function test_sql_bruto_nao_altera_ou_remove_auditoria_municipal(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            app(AuditTrail::class)->record('tenant', 'test.pgsql.immutable', SchoolYear::class);
            $registro = DB::connection('tenant')->table('audit_logs')
                ->where('action', 'test.pgsql.immutable')->first();

            $this->assertNotNull($registro);

            $this->assertBloqueado(fn () => DB::connection('tenant')->table('audit_logs')
                ->where('id', $registro->id)->update(['action' => 'adulterada']));
            $this->assertBloqueado(fn () => DB::connection('tenant')->table('audit_logs')
                ->where('id', $registro->id)->delete());
            $this->assertBloqueado(fn () => DB::connection('tenant')->table('audit_logs')->truncate());

            $this->assertSame('test.pgsql.immutable', DB::connection('tenant')
                ->table('audit_logs')->where('id', $registro->id)->value('action'));
        });
    }

    private function assertBloqueado(callable $mutation): void
    {
        try {
            $mutation();
            $this->fail('SQL bruto nao pode alterar a auditoria.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }
}
