@extends('layouts.municipal')

@php
    $editing = $member !== null;
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id];
    $title = $editing ? 'Editar servidor' : 'Novo servidor';
@endphp

@section('title', $title.' - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.show', $params) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('school.staff.index', $params) }}">Servidores</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">{{ $title }}</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div><span class="contexto">Equipe escolar</span><h1>{{ $title }}</h1><p>Cadastre somente dados necessários à gestão escolar. Alterações são auditadas sem registrar telefone nem e-mail em claro.</p></div>
    </div>
    <section class="folha formulario-aluno" aria-labelledby="titulo-form-servidor">
        <div class="folha-cabecalho"><div><h2 id="titulo-form-servidor">Dados do servidor</h2><p>Somente nome e função e situação são obrigatórios.</p></div></div>
        @if ($errors->any())
            <div class="erro" role="alert"><strong>Revise o formulário</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ $editing ? route('school.staff.update', [...$params, 'servidor' => $member->id]) : route('school.staff.store', $params) }}" novalidate>
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="formulario-grade">
                <div class="formulario-campo largo">
                    <label class="rotulo" for="full_name">Nome completo *</label>
                    <input class="entrada" id="full_name" name="full_name" required maxlength="160" value="{{ old('full_name', $member?->full_name) }}">
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="registration_number">Matrícula funcional (opcional)</label>
                    <input class="entrada" id="registration_number" name="registration_number" maxlength="40" value="{{ old('registration_number', $member?->registration_number) }}">
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="role">Função *</label>
                    <select class="entrada" id="role" name="role" required>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $member?->role ?? 'teacher') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="institutional_email">E-mail institucional (opcional)</label>
                    <input class="entrada" id="institutional_email" name="institutional_email" type="email" maxlength="160" value="{{ old('institutional_email', $member?->institutional_email) }}">
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="phone">Telefone com DDD (opcional)</label>
                    <input class="entrada" id="phone" name="phone" type="tel" maxlength="20" value="{{ old('phone', $member?->formattedPhone()) }}" placeholder="(11) 98765-4321">
                </div>
                <div class="formulario-campo">
                    <label class="rotulo" for="status">Situação *</label>
                    <select class="entrada" id="status" name="status" required>
                        <option value="active" @selected(old('status', $member?->status ?? 'active') === 'active')>Ativo</option>
                        <option value="inactive" @selected(old('status', $member?->status) === 'inactive')>Inativo</option>
                    </select>
                </div>
            </div>
            <div class="formulario-acoes">
                <button class="botao primario" type="submit">Salvar servidor</button>
                <a class="botao secundario" href="{{ route('school.staff.index', $params) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
