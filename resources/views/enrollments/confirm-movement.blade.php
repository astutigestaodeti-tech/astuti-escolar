@extends('layouts.municipal')

@php
    $titulo = match ($action) {
        'transferir' => 'Transferir matrícula',
        'encerrar' => 'Encerrar matrícula',
        default => 'Excluir cadastro',
    };
    $routeName = match ($action) {
        'transferir' => 'school.enrollments.transfer',
        'encerrar' => 'school.enrollments.end',
        default => 'school.enrollments.delete-registration',
    };
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'matricula' => $enrollment->id];
@endphp

@section('title', $titulo.' - '.$student->full_name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">Ficha do aluno</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $titulo }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Confirmação de movimentação escolar</span>
            <h1>{{ $titulo }}</h1>
            <p>Confira o aluno e a matrícula antes de confirmar. A operação será registrada na auditoria.</p>
        </div>
    </div>

    <section class="folha formulario-aluno" aria-labelledby="titulo-movimentacao">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-movimentacao">{{ $student->full_name }}</h2>
                <p>{{ $school->name }} · Ano letivo {{ $enrollment->schoolYear?->year ?? 'não informado' }} · Início {{ $enrollment->started_at?->format('d/m/Y') ?? 'não informado' }}</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="erro" role="alert">
                <x-icon name="alerta" :size="18" />
                <div>
                    <strong>Revise os dados</strong>
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route($routeName, $params) }}" novalidate>
            @csrf
            @if ($action === 'excluir-cadastro')
                @method('DELETE')
            @endif
            <input type="hidden" name="versao" value="{{ old('versao', $enrollment->row_token) }}">

            <div class="formulario-grade">
                @if ($action === 'transferir')
                    <div class="formulario-campo largo">
                        <label class="rotulo" for="escola_destino_id">Escola de destino *</label>
                        <select class="entrada" id="escola_destino_id" name="escola_destino_id" required>
                            <option value="">Escolha outra escola ativa da rede</option>
                            @foreach ($destinations as $destination)
                                <option value="{{ $destination->id }}" @selected(old('escola_destino_id') === $destination->id)>{{ $destination->name }}</option>
                            @endforeach
                        </select>
                        @error('escola_destino_id')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                @elseif ($action === 'encerrar')
                    <div class="formulario-campo largo">
                        <label class="rotulo" for="situacao">Motivo do encerramento *</label>
                        <select class="entrada" name="situacao" id="situacao" required>
                            <option value="">Escolha o motivo</option>
                            <option value="left_network" @selected(old('situacao') === 'left_network')>Transferência para fora da rede</option>
                            <option value="abandoned" @selected(old('situacao') === 'abandoned')>Abandono</option>
                            <option value="deceased" @selected(old('situacao') === 'deceased')>Falecimento</option>
                        </select>
                        @error('situacao')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulario-campo largo">
                        <label class="rotulo" for="destino">Destino fora da rede (opcional, apenas para transferência externa)</label>
                        <input class="entrada" id="destino" name="destino" type="text" maxlength="180" value="{{ old('destino') }}" placeholder="Identificação da rede de destino">
                        @error('destino')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                @else
                    <div class="formulario-campo largo">
                        <label class="rotulo" for="motivo">Justificativa por erro de cadastro (mínimo 15 caracteres) *</label>
                        <textarea class="entrada" id="motivo" name="motivo" rows="4" minlength="15" maxlength="1000" required>{{ old('motivo') }}</textarea>
                        @error('motivo')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if ($action !== 'excluir-cadastro')
                    <div class="formulario-campo">
                        <label class="rotulo" for="data">Data da movimentação *</label>
                        <input class="entrada" id="data" name="data" type="date" required
                               min="{{ $enrollment->started_at?->format('Y-m-d') ?? $enrollment->schoolYear?->start_date?->format('Y-m-d') }}"
                               max="{{ $enrollment->schoolYear?->end_date?->format('Y-m-d') }}"
                               value="{{ old('data') }}">
                        @error('data')<p class="campo-erro">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>

            <label class="confirmacao">
                <input type="checkbox" name="confirmar" value="1" required>
                <span>Conferi os dados e confirmo {{ $action === 'transferir' ? 'a transferência' : ($action === 'encerrar' ? 'o encerramento' : 'a exclusão lógica por erro de cadastro') }} desta matrícula.</span>
            </label>

            <div class="formulario-acoes">
                <button class="botao" type="submit">Confirmar {{ strtolower($titulo) }}</button>
                <a class="botao secundario" href="{{ route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">Cancelar</a>
            </div>
        </form>
    </section>

    <div class="nota">
        <x-icon name="escudo" />
        <div>
            <strong>{{ $action === 'excluir-cadastro' ? 'Exclusão lógica justificada' : 'Histórico escolar preservado' }}</strong>
            <p>É obrigatório confirmar o segundo fator. Somente dados fictícios são permitidos neste ambiente. A autoria e as alterações são auditadas.</p>
        </div>
    </div>
@endsection
