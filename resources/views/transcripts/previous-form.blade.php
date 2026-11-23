@php
    $editing = $record !== null;
    $grades = $record?->component_grades ?? [];
    $gradeRows = array_values(array_map(fn ($name, $grade) => ['name' => $name, 'value' => $grade], array_keys($grades), array_values($grades)));
    while (count($gradeRows) < 3) {
        $gradeRows[] = ['name' => '', 'value' => ''];
    }
@endphp

<form method="POST" action="{{ route('school.students.transcripts.previous.save', $params) }}" class="historico-formulario">
    @csrf
    @if ($editing)<input type="hidden" name="record" value="{{ $record->id }}">@endif
    <div class="historico-campos">
        <label for="historico-ano-{{ $suffix }}">Ano letivo *
            <input class="entrada" type="number" min="1900" max="2200" name="year" id="historico-ano-{{ $suffix }}" value="{{ $editing ? $record->school_year : old('year') }}" required>
        </label>
        <label for="historico-etapa-{{ $suffix }}">Etapa ou ano escolar *
            <input class="entrada" type="text" name="stage" id="historico-etapa-{{ $suffix }}" maxlength="120" value="{{ $editing ? $record->school_stage : old('stage') }}" required placeholder="Ex.: 2º ano">
        </label>
        <label for="historico-escola-{{ $suffix }}">Escola *
            <input class="entrada" type="text" name="school" id="historico-escola-{{ $suffix }}" maxlength="180" value="{{ $editing ? $record->school_name : old('school') }}" required>
        </label>
        <label for="historico-cidade-{{ $suffix }}">Município *
            <input class="entrada" type="text" name="city" id="historico-cidade-{{ $suffix }}" maxlength="120" value="{{ $editing ? $record->city : old('city') }}" required>
        </label>
        <label for="historico-uf-{{ $suffix }}">UF *
            <input class="entrada" type="text" name="state" id="historico-uf-{{ $suffix }}" maxlength="2" value="{{ $editing ? $record->state : old('state') }}" required placeholder="SP">
        </label>
        <label for="historico-resultado-{{ $suffix }}">Resultado *
            <input class="entrada" type="text" name="result" id="historico-resultado-{{ $suffix }}" maxlength="100" value="{{ $editing ? $record->result : old('result') }}" required placeholder="Ex.: Aprovado">
        </label>
        <label for="historico-frequencia-{{ $suffix }}">Frequência (%) opcional
            <input class="entrada" type="text" name="frequency" inputmode="decimal" id="historico-frequencia-{{ $suffix }}"
                   value="{{ $editing && $record->attendance_percent !== null ? number_format((float) $record->attendance_percent, 1, ',', '') : old('frequency') }}" placeholder="Ex.: 92,5">
        </label>
    </div>
    <p class="texto-ajuda">Notas e conceitos opcionais. Cada linha é um componente e seu valor, como 8,5 ou conceito B.</p>
    <div class="historico-notas-formulario">
        @foreach ($gradeRows as $i => $row)
            <label for="historico-comp-{{ $suffix }}-{{ $i }}">Componente {{ $i + 1 }}
                <input class="entrada" id="historico-comp-{{ $suffix }}-{{ $i }}"
                       name="grades[{{ $i }}][name]" maxlength="120" value="{{ $row['name'] }}" placeholder="Ex.: Matemática">
            </label>
            <label for="historico-valor-{{ $suffix }}-{{ $i }}">Nota ou conceito {{ $i + 1 }}
                <input class="entrada" id="historico-valor-{{ $suffix }}-{{ $i }}"
                       name="grades[{{ $i }}][value]" maxlength="50" value="{{ $row['value'] }}" placeholder="Ex.: 8,5 ou B">
            </label>
        @endforeach
    </div>
    <p class="texto-ajuda">Salvar ou editar um ano anterior exige confirmação recente do segundo fator e fica registrado na auditoria.</p>
    <button type="submit" class="botao secundario">{{ $editing ? 'Salvar alterações do ano' : 'Cadastrar ano anterior' }}</button>
</form>
