/* Preenche o endereço da escola a partir do ponto marcado no mapa (geocodificação
   reversa). Só preenche os campos: a pessoa confere e salva. Quando o endereço
   está vazio, faz isso sozinho assim que o ponto é marcado. */
(function () {
    const group = document.querySelector('[data-reverse-url]');
    const button = document.querySelector('[data-reverse-fill]');
    const lat = document.getElementById('latitude');
    const lon = document.getElementById('longitude');
    if (!group || !button || !lat || !lon) return;

    const status = group.querySelector('[data-postal-status]');
    const map = {
        street: 'address_street', number: 'address_number', district: 'address_district',
        city: 'address_city', state: 'address_state', postal_code: 'postal_code',
    };
    const field = (name) => document.getElementById(name);
    const emptyAddress = () => ['address_street', 'address_city'].every((name) => field(name) && field(name).value.trim() === '');

    async function fill(automatic) {
        if (lat.value === '' || lon.value === '') {
            if (!automatic) status.textContent = 'Marque a escola no mapa primeiro.';
            return;
        }
        button.disabled = true;
        status.textContent = 'Procurando o endereço deste ponto…';
        try {
            const url = new URL(group.dataset.reverseUrl, window.location.href);
            url.searchParams.set('lat', lat.value);
            url.searchParams.set('lon', lon.value);
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) {
                status.textContent = data.message || 'Não encontrei o endereço deste ponto. Preencha pelo CEP.';
                return;
            }
            Object.entries(map).forEach(([key, name]) => {
                const input = field(name);
                if (input && data[key]) input.value = data[key];
            });
            status.textContent = data.number
                ? 'Endereço preenchido pelo mapa. Confira e clique em "Salvar endereço e localização".'
                : 'Endereço preenchido pelo mapa. Confira, informe o número e clique em "Salvar endereço e localização".';
        } catch (error) {
            status.textContent = 'Não foi possível consultar agora. Preencha pelo CEP.';
        } finally {
            button.disabled = false;
        }
    }

    button.addEventListener('click', () => fill(false));

    // Endereço vazio: ao marcar ou arrastar o ponto, preenche sozinho.
    document.addEventListener('astuti:ponto-marcado', () => {
        if (emptyAddress()) fill(true);
    });
})();
