@if ($lessonPermissions['view'])
    <section class="folha aula-painel" aria-labelledby="aula-titulo" id="conteudo-aula">
        <div class="folha-cabecalho">
            <div><h2 id="aula-titulo">Conteúdo da aula</h2><p>{{ $date->format('d/m/Y') }}. Não registre nomes nem situações de alunos aqui.</p></div>
            <a class="botao secundario" href="{{ route('school.classes.lessons.report', $params) }}">Conteúdos ministrados</a>
        </div>
        <div class="folha-corpo aula-campos">
            @if (session('lesson_status'))<p class="status-sucesso" role="status">{{ session('lesson_status') }}</p>@endif
            @if ($errors->getBag('lesson')->any())
                <div class="nota" role="alert"><strong>Confira o conteúdo da aula.</strong><ul>@foreach ($errors->getBag('lesson')->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="GET" action="{{ route('school.classes.attendance.index', $params) }}" class="aula-filtros">
                <label>Data da aula <input class="entrada" type="date" name="data" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
                <label>Componente
                    <select class="entrada" name="componente_aula">
                        @if ($lessonPolivalent)<option value="polivalente" @selected($lessonComponent === null)>Polivalente (sem componente)</option>@endif
                        @foreach ($lessonComponents as $component)
                            <option value="{{ $component->id }}" @selected($lessonComponent === $component->id)>{{ $component->name }} @if (! $component->active)(inativo)@endif</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="botao secundario">Consultar</button>
            </form>
            @if ($lessonRecords->isNotEmpty())
                <ul class="aula-registros" aria-label="Conteúdos registrados nesta data">
                    @foreach ($lessonRecords as $lesson)
                        <li><a href="{{ route('school.classes.attendance.index', [...$params, 'data' => $date->toDateString(), 'componente_aula' => $lesson->curricular_component_id ?? 'polivalente']) }}#conteudo-aula">{{ $lesson->component?->name ?? 'Polivalente' }}</a> (versão {{ $lesson->version }})</li>
                    @endforeach
                </ul>
            @endif
            @if (! $lessonCanEdit)
                <p class="texto-ajuda">Consulta somente. @if ($lessonClosed) Ano letivo encerrado. @elseif (! $lessonDateAllowed) Data fora do ano letivo. @elseif (! $lessonPermissions['write']) Seu perfil não pode editar o conteúdo. @else Selecione um componente válido. @endif</p>
                @if ($lessonSelected)
                    <dl class="aula-leitura">
                        <div><dt>Conteúdo</dt><dd>{{ $lessonSelected->content }}</dd></div>
                        <div><dt>Atividade ou tarefa</dt><dd>{{ $lessonSelected->activity ?? 'Não registrada' }}</dd></div>
                        <div><dt>BNCC</dt><dd>{{ implode(', ', $lessonSelected->bncc_codes) ?: 'Não registrada' }}</dd></div>
                    </dl>
                @else
                    <p>Nenhum conteúdo registrado para esta seleção.</p>
                @endif
            @else
                <form method="POST" action="{{ route('school.classes.lessons.store', $params) }}" class="aula-campos">
                    @csrf
                    <input type="hidden" name="data" value="{{ $date->toDateString() }}">
                    <input type="hidden" name="componente" value="{{ $lessonComponent }}">
                    <input type="hidden" name="versao" value="{{ $lessonSelected?->version ?? 0 }}">
                    <label>Conteúdo trabalhado *
                        <textarea class="entrada" name="conteudo" rows="4" maxlength="1000" required>{{ $lessonSelected?->content }}</textarea>
                    </label>
                    <label>Atividade ou tarefa (opcional)
                        <textarea class="entrada" name="atividade" rows="3" maxlength="500">{{ $lessonSelected?->activity }}</textarea>
                    </label>
                    <label>Códigos BNCC (opcional)
                        <input class="entrada" type="text" name="bncc" maxlength="300" value="{{ implode(', ', $lessonSelected?->bncc_codes ?? []) }}" placeholder="EF01LP01, EI03ET01">
                    </label>
                    <p class="texto-ajuda">Até 10 códigos, separados por vírgula, espaço ou ponto e vírgula. Apenas o formato é conferido.</p>
                    @if ($lessonPermissions['director'] || $historical)
                        <label>Motivo do registro ou correção *
                            <textarea class="entrada" name="motivo" rows="2" minlength="10" maxlength="500" required></textarea>
                        </label>
                        <p class="texto-ajuda">Informe de 10 a 500 caracteres, sem dados de alunos. Exige segundo fator recente. <a href="{{ route('two-factor.challenge') }}">Confirmar novamente</a>, se necessário.</p>
                    @endif
                    <button type="submit" class="botao primario">Salvar conteúdo</button>
                </form>
            @endif
        </div>
    </section>
@endif
