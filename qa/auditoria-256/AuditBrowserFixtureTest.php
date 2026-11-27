<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

final class AuditBrowserFixtureTest extends TwoMunicipalityTestCase
{
    public function test_prepara_fixture_browser(): void
    {
        $this->assertSame('true', getenv('GITHUB_ACTIONS'));
        $this->assertSame('astuti_platform_test', getenv('PLATFORM_DB_DATABASE'));
        $this->assertSame('astuti_tenant_a_test', getenv('TENANCY_TEST_DB_A'));
        $user = $this->criarUsuario('secretaria-qa@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $user->forceFill(['two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'two_factor_confirmed_at' => now()])->save();
        app(TenantContext::class)->runFor($this->municipioA, fn () => SchoolYear::query()->create([
            'id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
        ]));
        $this->assertNotNull($user->id);
    }
}
