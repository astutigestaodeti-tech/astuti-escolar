<?php

namespace Tests\Feature\Students;

use App\Models\Platform\Municipality;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolStudentIndexTest extends TwoMunicipalityTestCase
{
    public function test_secretaria_consulta_somente_alunos_ativos_da_escola_autorizada(): void
    {
        $user = $this->criarUsuario('alunos-secretaria@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Alice da Unidade A');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Bruno da Outra Unidade A');
        $this->matricularAluno($this->municipioB, self::ESCOLA_B1, 'Carlos da Unidade B');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Dora com vínculo encerrado', 'completed');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSee('Alice da Unidade A')
            ->assertDontSee('Bruno da Outra Unidade A')
            ->assertDontSee('Carlos da Unidade B')
            ->assertDontSee('Dora com vínculo encerrado')
            ->assertSee('Novo aluno');

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_escola_sem_permissao_nao_expoe_alunos_por_url_direta(): void
    {
        $user = $this->criarUsuario('alunos-escopo@example.invalid');
        $this->vincular($user, $this->municipioA, 'teacher', [self::ESCOLA_A1]);
        $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Estudante restrito');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos')
            ->assertForbidden();
        $this->get('/m/municipio-b/escolas/'.self::ESCOLA_B1.'/alunos')
            ->assertForbidden();
        $this->get('/m/municipio-a/escolas/99999999-9999-4999-8999-999999999999/alunos')
            ->assertNotFound();
    }

    public function test_visitante_nao_pode_consultar_alunos(): void
    {
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertRedirect('/login');
    }

    public function test_tela_exibe_estado_vazio_quando_escola_nao_tem_vinculos_ativos(): void
    {
        $user = $this->criarUsuario('alunos-vazio@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSee('Nenhum aluno ativo')
            ->assertSee('Unidade A');
    }

    public function test_busca_por_nome_nao_interpreta_porcentagem_ou_sublinhado_como_curingas(): void
    {
        $user = $this->criarUsuario('alunos-busca@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Aline 100% fictícia');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Paulo Fictício');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Beatriz_A');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'BeatrizXA');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?busca=%25')
            ->assertOk()
            ->assertSee('Aline 100% fictícia')
            ->assertDontSee('Paulo Fictício')
            ->assertDontSee('Beatriz_A');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?busca=_')
            ->assertOk()
            ->assertSee('Beatriz_A')
            ->assertDontSee('BeatrizXA');
    }

    public function test_busca_por_nome_parcial_nao_diferencia_maiusculas_de_minusculas_no_postgresql(): void
    {
        $user = $this->criarUsuario('alunos-busca-caixa@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Maria do Campo');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'MARINA dos Lagos');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Bruno da Unidade');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Marcos de Outra Escola');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?busca=mari')
            ->assertOk()
            ->assertSee('Maria do Campo')
            ->assertSee('MARINA dos Lagos')
            ->assertDontSee('Bruno da Unidade')
            ->assertDontSee('Marcos de Outra Escola');
    }

    public function test_bancos_de_consulta_usam_icu_pt_br_e_indice_de_busca(): void
    {
        $platformLocale = DB::connection('platform')->selectOne('SELECT datlocprovider, datlocale FROM pg_database WHERE datname = current_database()');
        $this->assertSame('i', $platformLocale->datlocprovider);
        $this->assertSame('pt-br', strtolower((string) $platformLocale->datlocale));

        foreach ([$this->municipioA, $this->municipioB] as $municipio) {
            app(TenantContext::class)->runFor($municipio, function (): void {
                $tenantLocale = DB::connection('tenant')->selectOne('SELECT datlocprovider, datlocale FROM pg_database WHERE datname = current_database()');
                $this->assertSame('i', $tenantLocale->datlocprovider);
                $this->assertSame('pt-br', strtolower((string) $tenantLocale->datlocale));

                $index = DB::connection('tenant')->selectOne("SELECT indexdef FROM pg_indexes WHERE schemaname = 'public' AND tablename = 'students' AND indexname = 'students_full_name_unaccent_trgm_idx'");
                $this->assertNotNull($index);
                $this->assertStringContainsString('using gin', strtolower($index->indexdef));
                $this->assertStringContainsString('gin_trgm_ops', strtolower($index->indexdef));

                $function = DB::connection('tenant')->selectOne("SELECT provolatile FROM pg_proc WHERE oid = 'public.f_unaccent(text)'::regprocedure");
                $this->assertSame('i', $function->provolatile);
            });
        }
    }

    public function test_ordem_alfabetica_portuguesa_coloca_alvaro_antes_de_bruno_e_eder_antes_de_zelia(): void
    {
        $user = $this->criarUsuario('alunos-ordenacao-pt@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        foreach (['Ana de Teste', 'Bruno de Teste', 'Zélia de Teste', 'Álvaro de Teste', 'Éder de Teste'] as $nome) {
            $this->matricularAluno($this->municipioA, self::ESCOLA_A1, $nome);
        }

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSeeInOrder(['Álvaro de Teste', 'Ana de Teste', 'Bruno de Teste', 'Éder de Teste', 'Zélia de Teste']);
    }

    public function test_busca_sem_acentos_encontra_joao_e_preserva_escopo_escolar(): void
    {
        $user = $this->criarUsuario('alunos-joao-pt@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'João da Serra');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Maria da Serra');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Joao de Outra Escola');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?busca=joao')
            ->assertOk()
            ->assertSee('João da Serra')
            ->assertDontSee('Maria da Serra')
            ->assertDontSee('Joao de Outra Escola');
    }

    public function test_nome_exibido_e_escapado_no_html(): void
    {
        $user = $this->criarUsuario('alunos-escape@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, '<script>alert(1)</script>');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_multiplos_vinculos_do_mesmo_aluno_na_escola_nao_duplicam_linha(): void
    {
        $user = $this->criarUsuario('alunos-vinculos@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Helena Fictícia']);
            foreach ([2026, 2027] as $yearNumber) {
                $year = $this->anoLetivo($yearNumber);
                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $year->id,
                    'status' => 'active',
                ]);
            }
        });

        $response = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos');
        $response->assertOk()->assertSee('Helena Fictícia');
        $this->assertSame(1, $response->viewData('students')->total());
    }

    public function test_paginacao_mostra_vinte_e_cinco_registros_por_pagina(): void
    {
        $user = $this->criarUsuario('alunos-paginacao@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = $this->anoLetivo(2026);
            foreach (range(1, 26) as $index) {
                $student = Student::query()->create([
                    'id' => (string) Str::uuid(),
                    'full_name' => sprintf('Aluno %02d de Teste', $index),
                ]);
                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $year->id,
                    'status' => 'active',
                ]);
            }
        });

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSee('Aluno 01 de Teste')
            ->assertDontSee('Aluno 26 de Teste');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?page=2')
            ->assertOk()
            ->assertSee('Aluno 26 de Teste')
            ->assertDontSee('Aluno 01 de Teste');
    }

    public function test_gravacao_de_aluno_sem_segundo_fator_confirmado_e_recusada(): void
    {
        $user = $this->criarUsuario('alunos-gravacao@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->post('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos', [
            'full_name' => 'Não pode ser gravado',
        ])->assertForbidden();

        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn (): int => Student::query()->count()));
    }

    public function test_ficha_da_escola_oferece_acesso_a_consulta_de_alunos(): void
    {
        $user = $this->criarUsuario('alunos-ficha@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1)
            ->assertOk()
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertSee('class="rodape-versao"', false)
            // Fora do ambiente de desenvolvimento, o rodapé não revela o banco.
            ->assertDontSee('Plataforma:');
    }

    public function test_cartoes_da_escola_nao_sao_empilhados_pela_regra_da_ficha_do_aluno(): void
    {
        // A regra de grade que leva o histórico para a coluna lateral vale só
        // para a ficha do aluno; aplicada a todo ".cartao", empilhava os
        // cartões da escola uns sobre os outros.
        $css = (string) file_get_contents(public_path('css/astuti.css'));
        $this->assertStringNotContainsString('.ficha-escola>.cartao{', $css);
        $this->assertStringContainsString('.ficha-escola>.ficha-historico{grid-column:2', $css);

        $user = $this->criarUsuario('cartoes-escola@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1)
            ->assertOk()
            ->assertSeeInOrder(['Turmas', 'Alunos', 'Matrículas'])
            ->assertDontSee('permanecem indisponíveis');
    }

    /** @param array<string, mixed> $dados */
    private function alunoCom(string $nome, array $dados = [], ?string $transporte = null, array $detalhes = []): string
    {
        return app(TenantContext::class)->runFor($this->municipioA, function () use ($nome, $dados, $transporte, $detalhes): string {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $nome, ...$dados]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $this->anoLetivo(2027)->id,
                'status' => 'active',
                'transport_status' => $transporte,
                ...$detalhes,
            ]);

            return $student->id;
        });
    }

    public function test_lista_mostra_e_filtra_transporte_escolar_com_contagens(): void
    {
        $user = $this->criarUsuario('alunos-transporte@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->alunoCom('Ana Usa Ônibus', [], 'active');
        $this->alunoCom('Bia Também Usa', [], 'active');
        $this->alunoCom('Caio Cancelado', [], 'cancelled');
        $this->alunoCom('Duda Sem Informação');

        $lista = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos';
        $this->actingAs($user)->get($lista)
            ->assertOk()
            ->assertSeeInOrder(['Usa transporte', '2', 'Não solicitado', '0', 'Cancelado', '1', 'Sem informação', '1'])
            ->assertSee('Transporte escolar');

        $this->actingAs($user)->get($lista.'?transporte=usa')
            ->assertOk()
            ->assertSee('Ana Usa Ônibus')
            ->assertSee('Bia Também Usa')
            ->assertDontSee('Caio Cancelado')
            ->assertDontSee('Duda Sem Informação');

        $this->actingAs($user)->get($lista.'?transporte=sem_informacao')
            ->assertOk()->assertSee('Duda Sem Informação')->assertDontSee('Ana Usa Ônibus');

        $this->actingAs($user)->get($lista.'?transporte=qualquer')->assertSessionHasErrors('transporte');

        // A tela da escola resume quem usa o transporte e leva à lista filtrada.
        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1)
            ->assertOk()
            ->assertSee('2 alunos usam o transporte escolar.')
            ->assertSee($lista.'?transporte=usa');
    }

    public function test_contagem_e_filtro_de_transporte_ignoram_vinculos_arquivados_e_inativos(): void
    {
        $user = $this->criarUsuario('alunos-transporte-escopo@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $studentId = $this->alunoCom('Ana Fictícia Matrícula Ativa', [], 'not_requested');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Bruno Fictício Transferido', 'transferred');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Carla Fictícia Encerrada', 'completed');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Daniel Fictício Outra Escola');

        app(TenantContext::class)->runFor($this->municipioA, function () use ($studentId, $user): void {
            $old = StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $studentId,
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $this->anoLetivo(2026)->id,
                'status' => 'active',
                'transport_status' => 'active',
                'transport_adapted_vehicle' => true,
            ]);
            $old->deleteWithReason('Matrícula fictícia arquivada para teste.', $user->id);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $studentId,
                'school_id' => self::ESCOLA_A2,
                'school_year_id' => $this->anoLetivo(2025)->id,
                'status' => 'active',
                'transport_status' => 'active',
                'transport_adapted_vehicle' => true,
            ]);
        });

        $lista = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos';
        $response = $this->actingAs($user)->get($lista)->assertOk()
            ->assertSee('Ana Fictícia Matrícula Ativa')
            ->assertDontSee('Bruno Fictício Transferido')
            ->assertDontSee('Carla Fictícia Encerrada')
            ->assertDontSee('Daniel Fictício Outra Escola');
        $counts = $response->viewData('transportCounts');
        $this->assertSame(0, $counts['active'] ?? 0);
        $this->assertSame(1, $counts['not_requested']);
        $this->assertSame(0, $counts['adaptado']);
        $this->assertSame(1, $response->viewData('students')->total());

        foreach (['usa', 'adaptado'] as $filter) {
            $this->get($lista.'?transporte='.$filter)->assertOk()->assertDontSee('Ana Fictícia Matrícula Ativa')
                ->assertViewHas('students', fn ($students): bool => $students->total() === 0);
        }
        $this->get($lista.'?transporte=nao_solicitado')->assertOk()->assertSee('Ana Fictícia Matrícula Ativa');
        $this->get($lista.'/'.$studentId)->assertOk()->assertSee('Ano letivo 2027')
            ->assertDontSee('Ano letivo 2026')->assertDontSee('Ano letivo 2025');
    }

    public function test_busca_por_ra_cpf_e_nome_do_responsavel_sem_acento(): void
    {
        $user = $this->criarUsuario('alunos-busca@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->alunoCom('Aluno do RA', ['ra' => '000123456789-0/SP']);
        $this->alunoCom('Aluno do CPF', ['cpf' => '529.982.247-25']);
        $comMae = $this->alunoCom('Aluno da Mãe');
        $this->alunoCom('Aluno Sem Relação');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($comMae): void {
            $guardian = Guardian::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Conceição Fictícia Araújo', 'phone' => '11987654321']);
            StudentGuardian::query()->create(['id' => (string) Str::uuid(), 'student_id' => $comMae, 'guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary_contact' => true]);
        });

        $lista = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?busca=';
        $this->actingAs($user)->get($lista.urlencode('123456789'))->assertOk()->assertSee('Aluno do RA')->assertDontSee('Aluno Sem Relação');
        $this->actingAs($user)->get($lista.urlencode('52998224725'))->assertOk()->assertSee('Aluno do CPF')->assertDontSee('Aluno do RA');
        $this->actingAs($user)->get($lista.urlencode('529.982.247-25'))->assertOk()->assertSee('Aluno do CPF');
        $this->actingAs($user)->get($lista.urlencode('conceicao araujo'))->assertOk()->assertDontSee('Aluno da Mãe');
        $this->actingAs($user)->get($lista.urlencode('conceicao ficticia'))->assertOk()->assertSee('Aluno da Mãe')->assertDontSee('Aluno Sem Relação');
    }

    private function matricularAluno(Municipality $municipio, string $escola, string $nome, string $status = 'active'): void
    {
        app(TenantContext::class)->runFor($municipio, function () use ($escola, $nome, $status): void {
            $year = $this->anoLetivo(2026);
            $student = Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => $nome,
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => $escola,
                'school_year_id' => $year->id,
                'status' => $status,
            ]);
        });
    }

    private function anoLetivo(int $yearNumber): SchoolYear
    {
        return SchoolYear::query()->firstOrCreate(
            ['year' => $yearNumber],
            [
                'id' => (string) Str::uuid(),
                'start_date' => sprintf('%d-02-01', $yearNumber),
                'end_date' => sprintf('%d-12-18', $yearNumber),
                'status' => 'active',
            ],
        );
    }

    public function test_filtro_adaptado_e_linha_sao_restritos_a_escola_e_a_permissao_de_consulta(): void
    {
        $secretaria = $this->criarUsuario('transporte-adaptado@example.invalid');
        $this->vincular($secretaria, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->alunoCom('Ana Veículo Adaptado', [], 'active', [
            'transport_route' => 'Linha 12', 'transport_stop' => 'Parada das Flores',
            'transport_adapted_vehicle' => true,
        ]);
        $this->alunoCom('Bia Transporte Regular', [], 'active', [
            'transport_route' => 'Linha 15', 'transport_adapted_vehicle' => false,
        ]);
        $this->alunoCom('Caio Sem Informação');
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Segredo de Outra Escola']);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => self::ESCOLA_A2, 'school_year_id' => $this->anoLetivo(2027)->id,
                'status' => 'active', 'transport_status' => 'active',
                'transport_route' => 'Linha Reservada', 'transport_adapted_vehicle' => true,
            ]);
        });

        $lista = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos';
        $this->actingAs($secretaria)->get($lista)
            ->assertOk()->assertSee('Veículo adaptado')->assertSee('Linha 12')
            ->assertDontSee('Linha Reservada')->assertDontSee('Segredo de Outra Escola');
        $this->get($lista.'?transporte=adaptado')
            ->assertOk()->assertSee('Ana Veículo Adaptado')
            ->assertDontSee('Bia Transporte Regular')->assertDontSee('Caio Sem Informação')
            ->assertDontSee('Segredo de Outra Escola');
        $this->get($lista.'?busca=Ana&transporte=adaptado')
            ->assertOk()->assertSee('Ana Veículo Adaptado')->assertDontSee('Bia Transporte Regular');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos?transporte=adaptado')
            ->assertForbidden();

        $teacher = $this->criarUsuario('professor-transporte@example.invalid');
        $this->vincular($teacher, $this->municipioA, 'teacher', [self::ESCOLA_A1]);
        $this->actingAs($teacher)->get($lista.'?transporte=adaptado')
            ->assertOk()->assertSee('Ana Veículo Adaptado');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos?transporte=adaptado')->assertForbidden();
    }

    public function test_ficha_exibe_ponto_e_veiculo_adaptado_sem_alterar_a_view_principal(): void
    {
        $user = $this->criarUsuario('ficha-transporte@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $studentId = $this->alunoCom('Eva Transporte', [], 'active', [
            'transport_route' => 'Linha 08', 'transport_stop' => 'Ponto do Bosque',
            'transport_adapted_vehicle' => true,
        ]);

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertOk()->assertSee('Linha 08')->assertSee('Ponto do Bosque')->assertSee('Veículo adaptado');
    }
}
