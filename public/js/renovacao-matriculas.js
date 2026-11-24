document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('renovacao-turma');
    if (!form) return;

    const refresh = () => {
        const counts = new Map();
        form.querySelectorAll('.renovacao-destino').forEach(select => {
            if (select.value) counts.set(select.value, (counts.get(select.value) || 0) + 1);
        });
        form.querySelectorAll('[data-class]').forEach(row => {
            const total = Number(row.dataset.occupied) + (counts.get(row.dataset.class) || 0);
            row.querySelector('[data-projected]').textContent = String(total);
            row.querySelector('[data-capacity-warning]').hidden =
                row.dataset.capacity === '' || total <= Number(row.dataset.capacity);
        });
    };
    form.addEventListener('change', refresh);
    refresh();
});
