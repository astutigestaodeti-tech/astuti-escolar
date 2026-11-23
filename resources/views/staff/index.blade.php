@extends('layouts.municipal')

@php
    $params = ['municipio' => $municipality->slug, 'escola' => $school->id];
@endphp

@section('title', 'Servidores - '.$school->name)

@section('trilha')
    <a href="{{ route('schools.index', ['municipio' => $municipality->slug]) }}">Escolas</a>
    <x-icon name="seta" :size="16" />
    <a href="{{ route('schools.show', $params) }}">{{ $school->name }}</a>
    <x-icon name="seta" :size="16" />
    <span aria-current="page">Servidores</span>
@endsection

@section('content')
    <div class="folha-titulo">
        <div>
            <span class="contexto">Equipe escolar</span>
            <h1>Servidores da escola</h1>
            <p>Profissionais cadastrados nesta unidade. Atribuições às turmas são feitas na ficha de cada turma.</p>
        </div>
        @if ($canManage)
            <a class="botao primario" href="{{ route('school.staff.create', $params) }}">Novo servidor</a>
        @endif
    </div>

    @if (session('status'))
        <div class="status-sucesso" role="status">{{ session('status') }}</div>
    @endif

    <section class="folha" aria-labelledby="titulo-lista-servidores">
        <div class="folha-cabecalho"><div><h2 id="titulo-lista-servidores">Equipe da unidade</h2><p>Busca de nome sem diferença entre letras acentuadas e não acentuadas.</p></div></div>
        {{-- Mesmo padrão de busca da lista de alunos: campo com lupa e botão ao lado. --}}
        <form class="busca" method="GET" action="{{ route('school.staff.index', $params) }}" role="search">
            <div class="campo">
                <x-icon name="busca" :size="18" />
                <label class="sr-only" for="busca">Nome do servidor</label>
                <input id="busca" type="search" name="busca" value="{{ $search }}" maxlength="120" placeholder="Nome do servidor (acento não importa)" autocomplete="off">
            </div>
            <button class="botao" type="submit"><x-icon name="busca" :size="16" /> Pesquisar</button>
        </form>
        @if ($staff->isEmpty())
            <div class="vazio">{{ $search !== '' ? 'Nenhum servidor encontrado com esse nome.' : 'Nenhum servidor cadastrado nesta escola.' }}</div>
        @else
            <div class="rolagem">
                <table class="tabela servidores-tabela">
                    <thead><tr><th scope="col">Nome</th><th scope="col">Função</th><th scope="col">Matrícula funcional</th><th scope="col">Contato</th><th scope="col">Situação</th>@if ($canManage)<th scope="col">Ações</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($staff as $member)
                            <tr>
                                <td><strong>{{ $member->full_name }}</strong></td>
                                <td>{{ $roles[$member->role] ?? $member->role }}</td>
                                <td>{{ $member->registration_number ?: 'Não informada' }}</td>
                                <td>
                                    @if ($member->institutional_email)<a class="servidor-contato" href="mailto:{{ $member->institutional_email }}">{{ $member->institutional_email }}</a>@endif
                                    @if ($canManage && $member->phone)<a class="servidor-contato" href="tel:+55{{ $member->phone }}">{{ $member->formattedPhone() }}</a>@endif
                                    @if (! $member->institutional_email && ! ($canManage && $member->phone))<span class="texto-suave">Não informado</span>@endif
                                </td>
                                <td>{{ $member->status === 'active' ? 'Ativo' : 'Inativo' }}</td>
                                @if ($canManage)
                                    <td class="servidor-acoes">
                                        <a href="{{ route('school.staff.edit', [...$params, 'servidor' => $member->id]) }}">Editar</a>
                                        <a href="{{ route('school.staff.remove.form', [...$params, 'servidor' => $member->id]) }}">Arquivar</a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
