// Censo da turma: mostra só os campos que valem para o tipo escolhido e
// desativa componentes que o Inep não aceita para a etapa. O servidor confere tudo de novo.
(function () {
    const tipo = document.querySelector('[data-censo-tipo]');
    const etapa = document.querySelector('[data-censo-etapa]');
    function atualizar() {
        const curricular = tipo.value === '6' || tipo.value === '9';
        const atividade = tipo.value === '4' || tipo.value === '9';
        document.querySelectorAll('[data-so-curricular]').forEach(function (el) { el.hidden = ! curricular; });
        document.querySelectorAll('[data-so-atividade]').forEach(function (el) { el.hidden = ! atividade; });
        document.querySelectorAll('input[name="curricular_areas[]"]').forEach(function (caixa) {
            const permitidas = caixa.dataset.etapas ? caixa.dataset.etapas.split(',') : [];
            const ok = curricular && etapa.value !== '' && permitidas.indexOf(etapa.value) !== -1;
            caixa.disabled = ! ok;
            if (! ok) { caixa.checked = false; }
        });
    }
    tipo.addEventListener('change', atualizar);
    etapa.addEventListener('change', atualizar);
    atualizar();
    document.querySelector('[data-copiar-segunda]').addEventListener('click', function () {
        const inicio = document.querySelector('[data-dia="monday"][data-parte="inicio"]').value;
        const fim = document.querySelector('[data-dia="monday"][data-parte="fim"]').value;
        ['tuesday', 'wednesday', 'thursday', 'friday'].forEach(function (dia) {
            document.querySelector('[data-dia="' + dia + '"][data-parte="inicio"]').value = inicio;
            document.querySelector('[data-dia="' + dia + '"][data-parte="fim"]').value = fim;
        });
    });
})();
