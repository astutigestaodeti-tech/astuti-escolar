@extends('layouts.municipal')

@section('title', 'Saída de alunos - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Saída de alunos</span>
@endsection

@section('content')
    @php
        $parametrosEscola = ['municipio' => $municipality->slug, 'escola' => $school->id];
        $parentescos = \App\Http\Controllers\GuardianController::RELATIONSHIPS;
    @endphp

    <div class="folha-titulo">
        <div>
            <span class="contexto">Portão</span>
            <h1>Saída de alunos</h1>
            <p>Procure o aluno, compare o rosto de quem veio buscar com as fotos e registre a entrega.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status"><x-icon name="info" :size="18" /> {{ session('status') }}</div>
    @endif
    @if ($errors->has('pessoa'))
        <div class="status-erro" role="alert"><x-icon name="info" :size="18" /> {{ $errors->first('pessoa') }}</div>
    @endif
    @if ($errors->has('motivo'))
        <div class="status-erro" role="alert"><x-icon name="info" :size="18" /> {{ $errors->first('motivo') }}</div>
    @endif

    <section class="folha" aria-labelledby="titulo-busca-saida">
        <h2 id="titulo-busca-saida" class="sr-only">Procurar aluno</h2>
        <form class="busca saida-busca" method="GET" action="{{ route('school.exits.index', $parametrosEscola) }}" role="search">
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="busca-saida">Nome do aluno ou do responsável, ou RA</label>
                <input id="busca-saida" type="search" name="busca" value="{{ $search }}" placeholder="Nome do aluno ou do responsável, ou RA" maxlength="120" autocomplete="off" autofocus>
            </div>
            <button class="botao" type="submit"><x-icon name="busca" :size="16" /> Procurar</button>
        </form>

        @if ($search !== '' && mb_strlen($search) < 2)
            <p class="ficha-vazio saida-aviso">Digite pelo menos duas letras.</p>
        @elseif ($search !== '' && $students->isEmpty())
            <p class="ficha-vazio saida-aviso">Nenhum aluno desta escola encontrado. Confira o nome ou procure pelo RA.</p>
        @endif
        @if ($tooMany)
            <p class="ficha-vazio saida-aviso">Muitos alunos com esse nome. Digite também o sobrenome.</p>
        @endif
    </section>

    @foreach ($students as $aluno)
        @php
            $matricula = $aluno->enrollments->first();
            $responsaveis = $guardians->get($aluno->id, collect());
            $autorizadas = $authorizations->get($aluno->id, collect());
            $principal = $responsaveis->firstWhere('is_primary_contact', true) ?? $responsaveis->first();
            $ultimaSaida = $lastExits->get($aluno->id);
        @endphp
        <section class="folha saida-aluno" aria-labelledby="saida-aluno-{{ $aluno->id }}">
            <div class="saida-aluno-cabecalho">
                <x-foto-identificacao tipo="aluno" :id="$aluno->id" :escola="$school->id" :municipio="$municipality->slug" :nome="$aluno->full_name"
                    :tem-foto="$aluno->photo_path !== null" tamanho="grande" />
                <div>
                    <h2 id="saida-aluno-{{ $aluno->id }}">{{ $aluno->full_name }}</h2>
                    <p>Turma {{ $matricula?->schoolClass?->name ?? 'não informada' }}@if ($aluno->ra) · RA <span class="numero">{{ $aluno->ra }}</span>@endif</p>
                    @if ($ultimaSaida)
                        <p class="saida-ja-saiu"><span class="etiqueta conferir">Já saiu hoje às {{ $ultimaSaida->exited_at->format('H:i') }}</span> com {{ $ultimaSaida->person_name }}</p>
                    @endif
                </div>
            </div>

            @if ($responsaveis->isEmpty() && $autorizadas->isEmpty())
                <div class="saida-sem-autorizados">
                    <strong>Nenhuma pessoa autorizada cadastrada.</strong>
                    <p>Não entregue o aluno. Procure a secretaria da escola.</p>
                </div>
            @else
                <ul class="saida-pessoas">
                    @foreach ($responsaveis as $vinculo)
                        <li class="saida-pessoa">
                            <x-foto-identificacao tipo="responsavel" :id="$vinculo->guardian->id" :escola="$school->id" :municipio="$municipality->slug" :nome="$vinculo->guardian->full_name"
                                :tem-foto="$vinculo->guardian->photo_path !== null" tamanho="grande" />
                            <strong>{{ $vinculo->guardian->full_name }}</strong>
                            <span>{{ $parentescos[$vinculo->relationship] ?? 'Responsável' }}</span>
                            @if ($vinculo->guardian->rg_number)
                                <span class="saida-documento">RG: {{ \App\Validation\IdentityDocuments::formatRg($vinculo->guardian->rg_number) }} {{ $vinculo->guardian->rg_state }}</span>
                            @endif
                            <form method="POST" action="{{ route('school.exits.store', [...$parametrosEscola, 'aluno' => $aluno->id]) }}">
                                @csrf
                                <input type="hidden" name="pessoa" value="guardian:{{ $vinculo->guardian->id }}">
                                <button class="botao" type="submit">Entregar a {{ \Illuminate\Support\Str::before($vinculo->guardian->full_name, ' ') }}</button>
                            </form>
                        </li>
                    @endforeach
                    @foreach ($autorizadas as $pessoa)
                        <li class="saida-pessoa">
                            <x-foto-identificacao tipo="autorizado" :id="$pessoa->id" :escola="$school->id" :municipio="$municipality->slug" :nome="$pessoa->full_name"
                                :tem-foto="$pessoa->photo_path !== null" tamanho="grande" />
                            <strong>{{ $pessoa->full_name }}</strong>
                            <span>{{ $pessoa->relationship ?: 'Pessoa autorizada' }}@if ($pessoa->expires_at) · até {{ $pessoa->expires_at->format('d/m/Y') }}@endif</span>
                            @if ($pessoa->document)
                                <span class="saida-documento">Documento: {{ $pessoa->document }}</span>
                            @endif
                            <form method="POST" action="{{ route('school.exits.store', [...$parametrosEscola, 'aluno' => $aluno->id]) }}">
                                @csrf
                                <input type="hidden" name="pessoa" value="authorization:{{ $pessoa->id }}">
                                <button class="botao" type="submit">Entregar a {{ \Illuminate\Support\Str::before($pessoa->full_name, ' ') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <details class="saida-nao-esta">
                <summary>A pessoa não está na lista</summary>
                <p>Não entregue o aluno.
                    @if ($principal)
                        Ligue para o contato principal, {{ $principal->guardian->full_name }}:
                        <a class="numero" href="tel:+55{{ $principal->guardian->phone }}">{{ $principal->guardian->formatted_phone }}</a>.
                    @endif
                    Se a família autorizar outra pessoa, a secretaria cadastra em "Quem pode buscar", na ficha do aluno.
                </p>
            </details>
        </section>
    @endforeach

    <section class="folha" aria-labelledby="titulo-saidas-hoje">
        <div class="folha-cabecalho"><div><h2 id="titulo-saidas-hoje">Saídas de hoje</h2><p>{{ $validExitCount }} {{ $validExitCount === 1 ? 'saída' : 'saídas' }} nesta escola. Registrou por engano? Quem registrou desfaz em até {{ \App\Models\Tenant\StudentExit::UNDO_MINUTES }} minutos; depois disso, a secretaria ou a direção.</p></div></div>
        @if ($todayExits->isEmpty())
            <div class="vazio">Nenhuma saída registrada hoje.</div>
        @else
            <div class="rolagem">
                <table class="tabela">
                    <thead><tr><th scope="col">Horário</th><th scope="col">Aluno</th><th scope="col">Entregue a</th><th scope="col">Situação</th></tr></thead>
                    <tbody>
                    @foreach ($todayExits as $saida)
                        <tr @class(['saida-desfeita' => $saida->isCancelled()])>
                            <td class="numero">{{ $saida->exited_at->format('H:i') }}</td>
                            <td>{{ $saida->student?->full_name }}</td>
                            <td>{{ $saida->person_name }} <small class="texto-suave">({{ $saida->person_relationship }})</small></td>
                            <td>
                                @if ($saida->isCancelled())
                                    <span class="etiqueta neutra">Desfeita às {{ $saida->cancelled_at->format('H:i') }}</span>
                                @elseif ($undoable->has($saida->id))
                                    <details class="saida-desfazer">
                                        <summary>Desfazer</summary>
                                        <form method="POST" action="{{ route('school.exits.cancel', ['municipio' => $municipality->slug, 'escola' => $school->id, 'saida' => $saida->id]) }}">
                                            @csrf
                                            <label for="motivo-{{ $saida->id }}">Por que desfazer?</label>
                                            <input id="motivo-{{ $saida->id }}" name="motivo" type="text" minlength="10" maxlength="300" required placeholder="Ex.: cliquei no aluno errado">
                                            <button class="botao secundario" type="submit">Desfazer saída</button>
                                        </form>
                                    </details>
                                @else
                                    <span class="etiqueta ok">Entregue</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
