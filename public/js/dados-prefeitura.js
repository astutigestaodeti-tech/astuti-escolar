// Dados da Prefeitura: máscaras, conferência do CNPJ e prévia ao vivo do papel timbrado.
export function digits(value) {
    return String(value ?? '').replace(/\D/g, '');
}

export function cnpjIsValid(value) {
    const cnpj = digits(value);
    if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) return false;
    const weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    const digit = (numbers, list) => {
        const sum = list.reduce((total, weight, index) => total + Number(numbers[index]) * weight, 0);
        const rest = sum % 11;
        return rest < 2 ? 0 : 11 - rest;
    };
    const first = digit(cnpj, weights);
    const second = digit(cnpj.slice(0, 12) + first, [6, ...weights]);
    return cnpj.slice(12) === `${first}${second}`;
}

export function formatCnpj(value) {
    const d = digits(value).slice(0, 14);
    return d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
}

export function formatPhone(value) {
    const d = digits(value).slice(0, 11);
    if (d.length <= 2) return d;
    const middle = d.length === 11 ? 5 : 4;
    return `(${d.slice(0, 2)}) ${d.slice(2, 2 + middle)}${d.length > 2 + middle ? '-' + d.slice(2 + middle) : ''}`;
}

function boot() {
    const form = document.getElementById('form-prefeitura');
    if (!form) return;
    const value = (name) => (form.elements[name]?.value ?? '').trim();
    const cnpjStatus = document.querySelector('[data-cnpj-status]');
    const cnpjHelp = cnpjStatus?.textContent ?? '';

    form.querySelectorAll('[data-mascara]').forEach((input) => {
        input.addEventListener('input', () => {
            const formatted = input.dataset.mascara === 'cnpj' ? formatCnpj(input.value) : formatPhone(input.value);
            if (formatted !== input.value) input.value = formatted;
        });
    });

    function refresh() {
        const orgao = document.querySelector('.previa-papel .doc-timbre-orgao');
        if (orgao) orgao.textContent = value('department_name') || 'Secretaria Municipal de Educação';

        const street = [value('address_street'), value('address_number')].filter(Boolean).join(', ');
        const place = [street, value('address_complement'), value('address_district')].filter(Boolean).join(', ');
        const city = [value('address_city'), value('address_state')].filter(Boolean).join('/');
        const address = [place, city, value('postal_code') ? 'CEP ' + value('postal_code') : ''].filter(Boolean).join(' - ');
        const addressOut = document.querySelector('[data-previa-saida="endereco"]');
        if (addressOut) addressOut.textContent = address ? `${value('department_name') || 'Secretaria'}: ${address}` : '';

        const cnpj = digits(value('cnpj'));
        const contacts = [
            cnpj.length === 14 && cnpjIsValid(cnpj) ? 'CNPJ ' + formatCnpj(cnpj) : '',
            digits(value('phone')).length >= 10 ? 'Telefone ' + formatPhone(value('phone')) : '',
            value('email').toLowerCase(),
            value('website').replace(/^https?:\/\//i, '').replace(/\/$/, ''),
        ].filter(Boolean);
        const contactsOut = document.querySelector('[data-previa-saida="contatos"]');
        if (contactsOut) contactsOut.replaceChildren(...contacts.map((text) => Object.assign(document.createElement('span'), { textContent: text })));

        if (cnpjStatus) {
            if (cnpj.length === 0) cnpjStatus.textContent = cnpjHelp;
            else if (cnpj.length < 14) cnpjStatus.textContent = `Faltam ${14 - cnpj.length} números.`;
            else cnpjStatus.textContent = cnpjIsValid(cnpj) ? 'CNPJ conferido.' : 'Os dígitos verificadores não conferem. Confira o número.';
            cnpjStatus.dataset.estado = cnpj.length === 14 ? (cnpjIsValid(cnpj) ? 'ok' : 'erro') : '';
        }
    }

    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    // A busca do CEP preenche os campos sem evento de digitação: atualiza quando a mensagem dela muda.
    const postalStatus = form.querySelector('[data-postal-status]');
    if (postalStatus) new MutationObserver(refresh).observe(postalStatus, { childList: true, characterData: true, subtree: true });
    refresh();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}
