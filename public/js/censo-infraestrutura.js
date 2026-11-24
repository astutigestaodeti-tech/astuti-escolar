const form = document.querySelector('#infra-form');

if (form) {
    const atualizar = () => {
        for (const campo of form.querySelectorAll('[data-condicional]')) {
            const pai = form.querySelector('#campo-' + campo.dataset.condicional);
            const valor = pai?.type === 'checkbox' ? (pai.checked ? '1' : '0') : pai?.value;
            const aplica = valor === campo.dataset.esperado;
            campo.classList.toggle('infra-inaplicavel', !aplica);
            for (const entrada of campo.querySelectorAll('input')) {
                entrada.disabled = !aplica;
            }
        }
    };
    form.addEventListener('change', atualizar);
    atualizar();
}
