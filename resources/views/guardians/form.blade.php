@extends('layouts.municipal')

@php
    $editando = $link !== null;
    $titulo = $editando ? 'Editar responsável' : 'Adicionar responsável';
    $parametros = ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id];
    $voltar = route('school.students.show', $parametros).'#responsaveis';
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
            <span class="contexto">Secretaria escolar</span>
            <h1>{{ $titulo }}</h1>
            <p>Informe os dados de contato de {{ $student->full_name }}. As alterações ficam registradas na auditoria.</p>
        </div>
    </div>

    <section class="folha formulario-aluno" aria-labelledby="titulo-responsavel">
        <div class="folha-cabecalho">
            <div><h2 id="titulo-responsavel">Dados e vínculo familiar</h2><p>Os campos com * são obrigatórios.</p></div>
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
            ? route('school.students.guardians.update', [...$parametros, 'vinculo' => $link->id])
            : route('school.students.guardians.store', $parametros) }}">
            @csrf
            @if ($editando) @method('PUT') @endif

            <div class="formulario-grade">
                <div class="formulario-campo largo">
                    <label class="rotulo" for="full_name">Nome completo *</label>
                    <input class="entrada" id="full_name" name="full_name" maxlength="180" required value="{{ old('full_name', $guardian?->full_name) }}">
                    @error('full_name')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="cpf">CPF (opcional)</label>
                    <input class="entrada" id="cpf" name="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" aria-describedby="cpf-ajuda" value="{{ old('cpf', $guardian?->cpf) }}">
                    <p class="texto-ajuda" id="cpf-ajuda">Se o CPF já estiver cadastrado na rede, confira o nome conforme o documento. O sistema reaproveita o cadastro e atualiza telefone e e-mail quando informados. Aceita com ou sem pontuação.</p>
                    @error('cpf')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="phone">Telefone com DDD</label>
                    <input class="entrada" id="phone" name="phone" type="tel" inputmode="tel" data-guardian-phone maxlength="15" @if ($editando) required @endif placeholder="(11) 99999-9999" value="{{ old('phone', $guardian?->phone) }}">
                    <p class="texto-ajuda">O telefone é obrigatório para um novo cadastro. Ao vincular um CPF já existente, pode ficar em branco.</p>
                    @error('phone')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="email">E-mail (opcional)</label>
                    <input class="entrada" type="email" id="email" name="email" maxlength="255" value="{{ old('email', $guardian?->email) }}">
                    @error('email')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="relationship">Parentesco *</label>
                    <select class="entrada" id="relationship" name="relationship" required>
                        <option value="">Selecione</option>
                        @foreach ($relationships as $codigo => $nome)
                            <option value="{{ $codigo }}" @selected(old('relationship', $link?->relationship) === $codigo)>{{ $nome }}</option>
                        @endforeach
                    </select>
                    @error('relationship')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
                <div class="formulario-campo largo">
                    <label><input type="checkbox" name="is_legal_guardian" value="1" @checked(old('is_legal_guardian', $link?->is_legal_guardian ?? false))> É responsável legal pelo aluno</label>
                </div>
                <div class="formulario-campo largo">
                    <label><input type="checkbox" name="is_primary_contact" value="1" @checked(old('is_primary_contact', $link?->is_primary_contact ?? false))> É o contato principal deste aluno</label>
                    <p class="texto-ajuda">Cada aluno pode ter apenas um contato principal.</p>
                    @error('is_primary_contact')<p class="campo-erro">{{ $message }}</p>@enderror
                </div>
            </div>

            @if ($editando && $linkedStudentsCount > 1)
                <div class="nota" role="note">Este responsável está ligado a {{ $linkedStudentsCount }} alunos. Nome, CPF, telefone e e-mail alterados aqui valem para todos eles.</div>
            @endif

            <div class="formulario-acoes">
                <button class="botao" type="submit">{{ $editando ? 'Salvar alterações' : 'Vincular responsável' }}</button>
                <a class="botao secundario" href="{{ $voltar }}">Cancelar</a>
            </div>
        </form>
    </section>
    <script type="module" src="{{ asset('js/guardian-phone.js') }}?v={{ filemtime(public_path('js/guardian-phone.js')) }}"></script>
@endsection
