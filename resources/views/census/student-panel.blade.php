{{-- Dados que só o Censo Escolar pede (registro 30). Mesmo acesso do cadastro do aluno. --}}
@if ($canManage)
    @php
        $tabelasCenso = \App\Educacenso\Tabelas::do(\App\Educacenso\Tabelas::anoMaisRecente());
        $municipioTexto = function (?int $codigo) use ($tabelasCenso): string {
            $m = $codigo ? $tabelasCenso->municipio($codigo) : null;

            return $m ? $m['nome'].'/'.$m['uf'] : 'Não informado';
        };
        $c = $studentCensus;
        $filiacaoTexto = match ($c?->filiation) {
            1 => implode(' e ', array_filter([$c->parent1_name, $c->parent2_name])),
            0 => 'Não declarada',
            default => 'Não informada',
        };
    @endphp
    <section class="folha" id="dados-censo" aria-labelledby="titulo-dados-censo">
        <div class="folha-cabecalho">
            <div>
                <h2 id="titulo-dados-censo">Dados para o Censo Escolar</h2>
                <p>Filiação, municípios e localização no padrão do Educacenso.</p>
            </div>
            <a class="botao secundario" href="{{ route('school.students.census.edit', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id]) }}">{{ $c ? 'Editar dados do Censo' : 'Preencher dados do Censo' }}</a>
        </div>
        <div class="folha-corpo">
            @if ($c === null)
                <p class="texto-suave">Ainda não preenchidos. O Astuti sugere a filiação e os municípios a partir do cadastro.</p>
            @else
                <dl>
                    <div><dt>Filiação</dt><dd>{{ $filiacaoTexto }}</dd></div>
                    <div><dt>Município de nascimento</dt><dd>{{ $municipioTexto($c->birth_municipality_code) }}</dd></div>
                    <div><dt>Município de residência</dt><dd>{{ $municipioTexto($c->residence_municipality_code) }}</dd></div>
                    <div><dt>Localização diferenciada</dt><dd>{{ \App\Models\Tenant\StudentCensus::DIFFERENTIATED_LOCATIONS[$c->differentiated_location ?? 0] ?? 'Não informada' }}</dd></div>
                </dl>
            @endif
        </div>
    </section>
@endif
