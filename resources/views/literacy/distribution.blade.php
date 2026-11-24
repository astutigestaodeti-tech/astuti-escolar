<section class="folha">
    <div class="folha-cabecalho"><div><h2>Evolução entre bimestres</h2><p>Quantidade de estudantes por nível. Sem sondagem não equivale a nível inicial.</p></div></div>
    <div class="rolagem alfabetizacao-rolagem">
        <table class="tabela alfabetizacao-tabela">
            <thead><tr><th scope="col">Indicador</th>@foreach(range(1, 4) as $n)<th scope="col">{{ $n }}º bimestre</th>@endforeach</tr></thead>
            <tbody>
                <tr><th scope="row">Matrículas no recorte</th>@foreach($distribution as $row)<td>{{ $row['total'] }}</td>@endforeach</tr>
                <tr><th scope="row">Sondagens realizadas</th>@foreach($distribution as $row)<td>{{ $row['assessed'] }}</td>@endforeach</tr>
                <tr><th scope="row">Sem sondagem</th>@foreach($distribution as $row)<td>{{ $row['missing'] }}</td>@endforeach</tr>
                <tr><th scope="row" colspan="5">Escrita</th></tr>
                @foreach(\App\Models\Tenant\LiteracyAssessment::WRITING as $key => $label)
                    <tr><th scope="row">{{ $label }}</th>@foreach($distribution as $row)<td>{{ $row['writing'][$key] }}</td>@endforeach</tr>
                @endforeach
                <tr><th scope="row">Percentual alfabético entre avaliados</th>@foreach($distribution as $row)<td>{{ $row['alphabetic_pct'] === null ? 'Sem dados' : number_format($row['alphabetic_pct'], 1, ',', '.').'%' }}</td>@endforeach</tr>
                <tr><th scope="row" colspan="5">Leitura</th></tr>
                @foreach(\App\Models\Tenant\LiteracyAssessment::READING as $key => $label)
                    <tr><th scope="row">{{ $label }}</th>@foreach($distribution as $row)<td>{{ $row['reading'][$key] }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="folha-corpo"><p class="texto-ajuda">Comparação descritiva da composição das matrículas no recorte, não mede evolução individual nem substitui avaliações externas.</p></div>
</section>
