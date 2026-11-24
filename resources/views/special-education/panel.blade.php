{{-- Educação especial na ficha: só para quem gere a escola (mesmo acesso da saúde). --}}
@if ($canManageHealth)
    @push('head')
        <link rel="stylesheet" href="{{ asset('css/educacao-especial.css') }}?v={{ filemtime(public_path('css/educacao-especial.css')) }}">
    @endpush
    <section class="folha ficha-especial" id="educacao-especial" aria-labelledby="titulo-educacao-especial">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-educacao-especial">Educação especial e AEE</h2>
                <p>Categorias do Censo e atendimento especializado. Dado sensível: não copie para outros documentos.</p>
            </div>
            <a class="botao secundario" href="{{ route('school.students.special-education.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">Editar educação especial</a>
        </div>
        <div class="folha-corpo">
            @if ($specialEducation === null || ($specialEducation->conditions === [] && ($specialEducation->learning_disorders ?? []) === []))
                <p class="texto-suave">Nenhuma deficiência, TEA, altas habilidades ou transtorno de aprendizagem informado.</p>
            @else
                <ul class="especial-lista">
                    @foreach ($specialEducation->conditionLabels() as $rotulo)
                        <li class="etiqueta neutra">{{ $rotulo }}</li>
                    @endforeach
                </ul>
                <dl>
                    @if ($specialEducation->disorderLabels() !== [])
                        <div><dt>Transtornos de aprendizagem</dt><dd>{{ implode(', ', $specialEducation->disorderLabels()) }}</dd></div>
                    @endif
                    <div><dt>AEE</dt><dd>{{ $specialEducation->aeeLabel() }}</dd></div>
                    <div><dt>Profissional de apoio</dt><dd>{{ $specialEducation->support_professional === null ? 'Não informado' : ($specialEducation->support_professional ? 'Sim' : 'Não') }}</dd></div>
                    <div><dt>Recursos para provas</dt><dd>{{ $specialEducation->resourceLabels() === [] ? 'Nenhum informado' : implode(', ', $specialEducation->resourceLabels()) }}</dd></div>
                </dl>
            @endif
        </div>
    </section>
@endif
