@props(['tipo', 'id', 'escola', 'municipio', 'nome', 'temFoto' => false, 'podeAlterar' => false, 'tamanho' => 'pequena', 'aviso' => null])
@php
    $rotaFoto = ['municipio' => $municipio, 'escola' => $escola, 'tipo' => $tipo, 'id' => $id];
    $partesNome = preg_split('/\s+/u', trim((string) $nome)) ?: [];
    $iniciais = mb_strtoupper(mb_substr($partesNome[0] ?? '', 0, 1).(count($partesNome) > 1 ? mb_substr(end($partesNome), 0, 1) : ''));
@endphp
{{-- Foto de identificação: uso interno, servida só a quem pode ver a escola. --}}
<div {{ $attributes->class(['foto-id', 'foto-id-'.$tamanho]) }}>
    @if ($temFoto)
        <img class="foto-id-imagem" src="{{ route('school.photos.show', $rotaFoto) }}" alt="Foto de {{ $nome }}" loading="lazy" decoding="async">
    @else
        <span class="foto-id-vazia" aria-hidden="true">{{ $iniciais }}</span>
    @endif
    @if ($aviso)
        <span class="foto-id-aviso">{{ $aviso }}</span>
    @endif
    @if ($podeAlterar)
        <details class="foto-id-acoes">
            <summary>{{ $temFoto ? 'Trocar foto' : 'Adicionar foto' }}</summary>
            <form method="POST" action="{{ route('school.photos.update', $rotaFoto) }}" enctype="multipart/form-data">
                @csrf
                <label class="sr-only" for="foto-{{ $tipo }}-{{ $id }}">Foto de {{ $nome }}</label>
                <input id="foto-{{ $tipo }}-{{ $id }}" type="file" name="foto" accept="image/jpeg,image/png,image/webp" required data-foto-reduzir data-limite-bytes="{{ \App\Support\UploadLimit::bytes() }}">
                <button class="botao secundario" type="submit">Enviar foto</button>
                <p class="foto-id-situacao" aria-live="polite"></p>
            </form>
            @if ($temFoto)
                <form method="POST" action="{{ route('school.photos.destroy', $rotaFoto) }}">
                    @csrf
                    <button class="foto-id-remover" type="submit">Remover foto</button>
                </form>
            @endif
            <p class="texto-ajuda">Uso interno, para conferir quem entra e quem busca o aluno. Não é publicada nem compartilhada.</p>
        </details>
    @endif
</div>
@if ($podeAlterar)
    @once
        <script type="module" src="{{ asset('js/foto-identificacao.js') }}?v={{ filemtime(public_path('js/foto-identificacao.js')) }}"></script>
    @endonce
@endif
