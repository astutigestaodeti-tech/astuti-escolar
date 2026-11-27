export function postalCodeDigits(value) {
    if (typeof value !== 'string') return null;
    const clean = value.trim();
    if (!/^(?:\d{8}|\d{5}-\d{3})$/.test(clean)) return null;
    return clean.replace('-', '');
}

export function formatPostalCode(value) {
    const digits = postalCodeDigits(value);
    return digits === null ? value : digits.slice(0, 5) + '-' + digits.slice(5);
}

export function shouldApplySuggestion(current, valueAtLookup, suggestion, force = true) {
    return typeof suggestion === 'string' && suggestion.trim() !== '' && current === valueAtLookup
        && (force || current.trim() === '');
}

export function postalLookupMessage(data) {
    if (data.source === 'stale') {
        return 'Endereço sugerido com dados salvos anteriormente. Confirme antes de gravar.';
    }

    if (!data.street?.trim() && !data.district?.trim() && data.city?.trim() && data.state?.trim()) {
        return `CEP geral do município. Encontramos ${data.city}/${data.state}. Como este CEP não identifica uma rua específica, informe o logradouro, número e bairro para completar o endereço.`;
    }

    return 'Endereço encontrado. Confira os dados e complete o número, se necessário.';
}

function bootPostalLookup() {
    const group = document.querySelector('[data-postal-lookup-url]');
    if (!group) return;

    const cep = group.querySelector('[name="postal_code"]');
    const button = group.querySelector('[data-postal-search]');
    const status = group.querySelector('[data-postal-status]');
    const fields = {
        street: group.querySelector('[name="address_street"]'),
        district: group.querySelector('[name="address_district"]'),
        city: group.querySelector('[name="address_city"]'),
        state: group.querySelector('[name="address_state"]'),
    };
    if (!cep || !button || !status || Object.values(fields).some((field) => !field)) return;

    let timer = null;
    let pending = null;
    let sequence = 0;
    let lastSuccess = null;

    function cancelPending() {
        if (timer) clearTimeout(timer);
        timer = null;
        if (pending) pending.abort();
        pending = null;
        button.disabled = false;
        sequence += 1;
    }

    async function lookup(force = false) {
        cancelPending();
        const digits = postalCodeDigits(cep.value);
        if (!digits) {
            status.textContent = 'Informe um CEP com oito números.';
            return;
        }
        if (!force && digits === lastSuccess) return;

        const thisSequence = sequence;
        const before = Object.fromEntries(Object.entries(fields).map(([key, field]) => [key, field.value]));
        const controller = new AbortController();
        pending = controller;
        button.disabled = true;
        status.textContent = 'Consultando CEP…';

        try {
            const url = group.dataset.postalLookupUrl.replace('__CEP__', digits);
            const response = await fetch(url, {
                signal: controller.signal,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (thisSequence !== sequence || postalCodeDigits(cep.value) !== digits) return;
            if (!response.ok) {
                status.textContent = data.message || 'Não foi possível consultar o CEP. Preencha o endereço manualmente.';
                return;
            }
            let preserved = false;
            Object.entries(fields).forEach(([key, field]) => {
                if (shouldApplySuggestion(field.value, before[key], data[key], force)) {
                    field.value = data[key];
                } else if (typeof data[key] === 'string' && data[key].trim() !== '' && field.value !== data[key]) {
                    preserved = true;
                }
            });
            cep.value = formatPostalCode(cep.value);
            lastSuccess = digits;
            status.textContent = postalLookupMessage(data) + (preserved
                ? ' Campos já preenchidos foram mantidos. Confira o endereço antes de gravar.'
                : '');
        } catch (error) {
            if (error.name !== 'AbortError' && thisSequence === sequence) {
                status.textContent = 'Consulta indisponível. Você pode preencher o endereço manualmente.';
            }
        } finally {
            if (thisSequence === sequence) {
                pending = null;
                button.disabled = false;
            }
        }
    }

    cep.addEventListener('input', () => {
        cancelPending();
        lastSuccess = null;
        cep.value = formatPostalCode(cep.value);
        status.textContent = '';
        if (postalCodeDigits(cep.value)) timer = setTimeout(() => lookup(), 350);
    });
    button.addEventListener('click', () => lookup(true));
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootPostalLookup);
    } else {
        bootPostalLookup();
    }
}
