@extends('layouts.municipal')

@php
    $editando = $authorization !== null;
    $titulo = $editando ? 'Editar autorização de retirada' : 'Adicionar pessoa autorizada';
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $voltar = route('school.students.show', $parametros).'#quem-pode-buscar';
@endphp

@section('title', $titulo.' - '.$student->full_name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', ['municipio' => $municipality->slug, 'escola' => $school->id]) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.students.show', $parametros) }}">{{ $student->full_name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $titulo }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Segurança na saída</span>
            <h1>{{ $titulo }}</h1>
            <p>Aluno: {{ $student->full_name }}. Os responsáveis já vinculados não precisam de cadastro adicional.</p>
        </div>
    </div>

    <section class="folha formulario-aluno" aria-labelledby="titulo-pessoa-autorizada">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-pessoa-autorizada">Dados para conferência na saída</h2><p>Somente nome e telefone com DDD são obrigatórios. Utilize apenas dados fictícios neste ambiente.</p></div>
        </div>

        @if ($errors->any())
            <div class="erro" role="alert">
                <x-icon name="alerta" :size="18" />
                <div><strong>Revise o formulário</strong><ul>
                    @foreach ($errors->all() as $mensagem)
                        <li>{{ $mensagem }}</li>
                    @endforeach
                </ul></div>
            </div>
        @endif

        <form method="POST" novalidate action="{{ $editando
            ? route('school.students.pickups.update', [...$parametros, 'autorizacao' => $authorization->id])
            : route('school.students.pickups.store', $parametros) }}">
            @csrf
            @if ($editando) @method('PUT') @endif
            <div class="formulario-grade">
                <div class="formulario-campo largo">
                    <label class="rotulo" for="full_name">Nome completo *</label>
                    <input class="entrada" id="full_name" name="full_name" maxlength="180" required value="{{ old('full_name', $authorization?->full_name) }}">
                    @error('full_name')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="relationship">Parentesco ou relação</label>
                    <input class="entrada" id="relationship" name="relationship" maxlength="80" placeholder="Ex.: vizinha, tio, van escolar" value="{{ old('relationship', $authorization?->relationship) }}">
                    @error('relationship')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="phone">Telefone com DDD *</label>
                    <input class="entrada" id="phone" name="phone" type="tel" inputmode="tel" data-guardian-phone maxlength="15" required placeholder="(11) 98765-4321" value="{{ old('phone', $authorization?->phone) }}">
                    @error('phone')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="document">Documento para conferência (RG ou CPF)</label>
                    <input class="entrada" id="document" name="document" maxlength="40" aria-describedby="document-ajuda" value="{{ old('document', $authorization?->document) }}">
                    <p class="texto-ajuda" id="document-ajuda">Opcional, somente para a equipe conferir a identidade no portão.</p>
                    @error('document')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="expires_at">Autorizado até</label>
                    <input class="entrada" id="expires_at" name="expires_at" type="date" aria-describedby="expires-ajuda" value="{{ old('expires_at', $authorization?->expires_at?->format('Y-m-d')) }}">
                    <p class="texto-ajuda" id="expires-ajuda">Opcional. A autorização vale até o fim deste dia; depois aparece como vencida.</p>
                    @error('expires_at')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo largo">
                    <label class="rotulo" for="notes">Observação curta</label>
                    <textarea class="entrada" id="notes" name="notes" maxlength="255" rows="3">{{ old('notes', $authorization?->notes) }}</textarea>
                    @error('notes')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="formulario-acoes">
                <button class="botao" type="submit">{{ $editando ? 'Salvar alterações' : 'Salvar autorização' }}</button>
                <a class="botao secundario" href="{{ $voltar }}">Cancelar</a>
            </div>
        </form>
    </section>
    <script type="module" src="{{ asset('js/guardian-phone.js') }}?v={{ filemtime(public_path('js/guardian-phone.js')) }}"></script>
@endsection
