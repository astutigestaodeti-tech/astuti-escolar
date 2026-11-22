@extends('layouts.municipal')

@section('title', 'Ficha de '.$student->full_name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.index', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">Alunos</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Ficha do aluno</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Consulta individual</span>
            <h1>Ficha do aluno</h1>
            <p>Informações de consulta da unidade {{ $school->name }}.</p>
        </div>
        <div class="acoes-titulo">
            <span class="etiqueta ok">Vínculo ativo</span>
            @if ($canManage)
                <a class="botao secundario" href="{{ route('school.students.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}"><x-icon name="ficha" :size="16" /> Editar dados</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif

    <div class="ficha-escola">
        @php
            $logradouro = trim(implode(', ', array_filter([$student->address_street, $student->address_number])));
            if ($student->address_complement) {
                $logradouro = trim($logradouro.($logradouro !== '' ? ' - ' : '').$student->address_complement);
            }
            $cidade = trim(implode('/', array_filter([$student->address_city, $student->address_state])));
            $zona = ['urban' => 'Urbana', 'rural' => 'Rural'][$student->residence_zone ?? ''] ?? null;
            $temEndereco = $logradouro !== '' || $student->address_district || $cidade !== '' || $student->postal_code || $zona;
            $editarEndereco = route('school.students.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]).'#endereco';
        @endphp
        <section class="folha" aria-labelledby="titulo-dados-aluno">
            <div class="folha-cabecalho"><div><h2 id="titulo-dados-aluno">Dados do aluno</h2><p>Informações disponíveis no cadastro municipal.</p></div></div>
            <div class="ficha-grupos">
                <div class="ficha-grupo" aria-labelledby="titulo-identificacao">
                    <h3 id="titulo-identificacao">Identificação</h3>
                    <dl>
                        <div><dt>Nome completo</dt><dd>{{ $student->full_name }}</dd></div>
                        <div><dt>RA</dt><dd class="numero">{{ $student->ra ?? 'Não informado' }}</dd></div>
                        <div><dt>Ano de nascimento</dt><dd class="numero">{{ $student->birth_date?->format('Y') ?? 'Não informado' }}</dd></div>
                        <div><dt>Escola</dt><dd>{{ $school->name }}</dd></div>
                    </dl>
                </div>
                <div class="ficha-grupo" aria-labelledby="titulo-endereco-aluno">
                    <h3 id="titulo-endereco-aluno">Endereço</h3>
                    @if ($temEndereco)
                        <dl>
                            <div><dt>Rua e número</dt><dd>{{ $logradouro !== '' ? $logradouro : 'Não informado' }}</dd></div>
                            <div><dt>Bairro</dt><dd>{{ $student->address_district ?? 'Não informado' }}</dd></div>
                            <div><dt>Cidade</dt><dd>{{ $cidade !== '' ? $cidade : 'Não informada' }}</dd></div>
                            <div><dt>CEP</dt><dd class="numero">{{ $student->postal_code ?? 'Não informado' }}</dd></div>
                            <div><dt>Zona</dt><dd>{{ $zona ?? 'Não informada' }}</dd></div>
                        </dl>
                    @else
                        <p class="ficha-vazio">Endereço ainda não informado.
                            @if ($canManage)
                                <a href="{{ $editarEndereco }}">Informar endereço</a>
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </section>

        <section class="folha cartao" aria-labelledby="titulo-vinculos">
            <span class="acao-icone"><x-icon name="turmas" /></span>
            <h2 id="titulo-vinculos">Histórico de vínculos nesta escola</h2>
            <p>Anos letivos, situações e datas registradas nesta unidade.</p>
            <ul class="documentos vinculos-aluno">
                @foreach ($enrollments as $enrollment)
                    @php
                        $situacao = match ($enrollment->status) {
                            'active' => 'Ativo',
                            'completed' => 'Concluído',
                            'cancelled' => 'Cancelado',
                            'transferred' => 'Transferido',
                            'left_network' => 'Transferido para fora da rede',
                            'abandoned' => 'Abandono',
                            'deceased' => 'Falecimento',
                            default => 'Outro',
                        };
                    @endphp
                    <li>
                        <span class="etiqueta neutra numero">Ano letivo {{ $enrollment->schoolYear?->year ?? 'Não informado' }}</span>
                        <span class="etiqueta {{ $enrollment->status === 'active' ? 'ok' : 'neutra' }}">{{ $situacao }}</span>
                        <div class="vinculo-datas"><small>Turma: {{ $enrollment->schoolClass?->name ?? 'Não informada' }}</small></div>
                        @if ($canManage && $enrollment->status === 'active')
                            <a href="{{ route('school.students.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'matricula' => $enrollment->id]) }}">Editar turma desta matrícula</a>
                        @endif
                        @if ($enrollment->started_at || $enrollment->ended_at)
                            <div class="vinculo-datas">
                                @if ($enrollment->started_at)
                                    <small>Início: {{ $enrollment->started_at->format('d/m/Y') }}</small>
                                @endif
                                @if ($enrollment->ended_at)
                                    <small>Encerramento: {{ $enrollment->ended_at->format('d/m/Y') }}</small>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    @if ($canManage)
        @foreach ($enrollments->where('status', 'active') as $enrollment)
            @php
                $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'matricula' => $enrollment->id];
            @endphp
            <div class="formulario-acoes" aria-label="Movimentações da matrícula do ano letivo {{ $enrollment->schoolYear?->year }}">
                <a class="botao secundario" href="{{ route('school.enrollments.transfer.form', $params) }}">Transferir matrícula</a>
                <a class="botao secundario" href="{{ route('school.enrollments.end.form', $params) }}">Encerrar matrícula</a>
                @if ($canDeleteEnrollment)
                    <a class="botao secundario" href="{{ route('school.enrollments.delete-registration.form', $params) }}">Excluir cadastro</a>
                @endif
            </div>
        @endforeach
    @endif

    @if ($canManage)
        <div class="nota"><x-icon name="escudo" /><div><strong>Alterações exigem o segundo fator</strong><p>Para editar, confirme o código do seu aplicativo autenticador. Toda alteração fica registrada no histórico. Utilize apenas informações fictícias.</p></div></div>
    @else
        <div class="nota"><x-icon name="escudo" /><div><strong>Somente consulta</strong><p>Sua conta pode consultar esta ficha, mas não alterar dados escolares. Utilize apenas informações fictícias.</p></div></div>
    @endif
@endsection
