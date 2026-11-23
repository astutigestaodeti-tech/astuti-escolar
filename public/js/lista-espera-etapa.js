/* A interface sugere a etapa, mas o servidor sempre recalcula e valida. */
const birthday = document.querySelector('[data-stage-input]');
const output = document.querySelector('[data-stage-output]');

if (birthday && output) {
    const labels = ['Berçário', 'Berçário', 'Maternal I', 'Maternal II', 'Pré I', 'Pré II'];
    const cutoffText = birthday.dataset.cutoff;

    function updateStage() {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(birthday.value);
        const cutoff = /^(\d{4})-(\d{2})-(\d{2})$/.exec(cutoffText || '');

        if (!match || !cutoff) {
            output.textContent = 'Informe a data de nascimento para calcular a etapa.';
            return;
        }

        const birth = new Date(birthday.value + 'T00:00:00Z');
        const limit = new Date(cutoffText + 'T00:00:00Z');
        if (Number.isNaN(birth.getTime()) || birth.toISOString().slice(0, 10) !== birthday.value || birth > limit) {
            output.textContent = 'Data não elegível para o ano de ingresso configurado.';
            return;
        }

        let age = limit.getUTCFullYear() - birth.getUTCFullYear();
        if (limit.getUTCMonth() < birth.getUTCMonth() ||
            (limit.getUTCMonth() === birth.getUTCMonth() && limit.getUTCDate() < birth.getUTCDate())) age -= 1;

        output.textContent = labels[age]
            ? 'Etapa calculada: ' + labels[age] + '. Confira e confirme abaixo.'
            : 'Idade fora das etapas de educação infantil desta fila.';
    }

    birthday.addEventListener('change', updateStage);
    birthday.addEventListener('input', updateStage);
    updateStage();
}
