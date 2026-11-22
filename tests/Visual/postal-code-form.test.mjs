import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatPostalCode, postalCodeDigits, postalLookupMessage, shouldApplySuggestion } from '../../public/js/student-postal-code.js';

test('CEP com oito algarismos recebe hifen sem perder zeros iniciais', () => {
    assert.equal(formatPostalCode('07400000'), '07400-000');
    assert.equal(formatPostalCode('07400-000'), '07400-000');
    assert.equal(formatPostalCode('07400'), '07400');
    assert.equal(postalCodeDigits('07400-000'), '07400000');
    assert.equal(postalCodeDigits('123'), null);
    assert.equal(postalCodeDigits('123456789'), null);
});

test('sugestao nao apaga valor manual nem numero ou complemento', () => {
    assert.equal(shouldApplySuggestion('Bairro informado', 'Bairro informado', 'Centro'), true);
    assert.equal(shouldApplySuggestion('Corrigido pelo usuario', 'Bairro informado', 'Centro'), false);
    assert.equal(shouldApplySuggestion('Rua existente', 'Rua existente', ''), false);
    assert.equal(shouldApplySuggestion('Rua existente', 'Rua existente', null), false);
});

test('CEP geral explica por que rua e bairro precisam ser preenchidos', () => {
    const mensagem = postalLookupMessage({
        source: 'online', street: null, district: '', city: 'Santa Isabel', state: 'SP',
    });

    assert.equal(mensagem, 'CEP geral do município. Encontramos Santa Isabel/SP. Como este CEP não identifica uma rua específica, informe o logradouro, número e bairro para completar o endereço.');
});

test('CEP com rua mantém mensagem normal e cache antigo mantém alerta', () => {
    const enderecoCompleto = { source: 'online', street: 'Avenida Paulista', district: 'Bela Vista', city: 'São Paulo', state: 'SP' };
    assert.equal(postalLookupMessage(enderecoCompleto), 'Endereço encontrado. Confira os dados e complete o número, se necessário.');
    assert.equal(postalLookupMessage({ ...enderecoCompleto, source: 'stale', street: null }), 'Endereço sugerido com dados salvos anteriormente. Confirme antes de gravar.');
    assert.equal(postalLookupMessage({ source: 'online', street: null, district: null, city: '', state: '' }), 'Endereço encontrado. Confira os dados e complete o número, se necessário.');
});

test('consulta de CEP geral mostra orientacao no formulario sem preencher rua e bairro', async () => {
    const previousDocument = globalThis.document;
    const previousFetch = globalThis.fetch;
    const handlers = {};
    const cep = { value: '07500000', addEventListener: () => {} };
    const button = { disabled: false, addEventListener: (event, handler) => { handlers[event] = handler; } };
    const status = { textContent: '' };
    const fields = Object.fromEntries(['street', 'district', 'city', 'state'].map((key) => [key, { value: '' }]));
    const nodes = {
        '[name="postal_code"]': cep,
        '[data-postal-search]': button,
        '[data-postal-status]': status,
        '[name="address_street"]': fields.street,
        '[name="address_district"]': fields.district,
        '[name="address_city"]': fields.city,
        '[name="address_state"]': fields.state,
    };

    try {
        globalThis.document = {
            readyState: 'complete',
            querySelector: () => ({ dataset: { postalLookupUrl: '/alunos/cep/__CEP__' }, querySelector: (selector) => nodes[selector] }),
        };
        globalThis.fetch = async () => ({
            ok: true,
            json: async () => ({ source: 'online', street: null, district: null, city: 'Santa Isabel', state: 'SP' }),
        });
        await import('../../public/js/student-postal-code.js?simular-cep-geral=' + Date.now());
        await handlers.click();

        assert.match(status.textContent, /CEP geral do município\. Encontramos Santa Isabel\/SP/);
        assert.equal(fields.city.value, 'Santa Isabel');
        assert.equal(fields.state.value, 'SP');
        assert.equal(fields.street.value, '');
        assert.equal(fields.district.value, '');
    } finally {
        globalThis.document = previousDocument;
        globalThis.fetch = previousFetch;
    }
});

test('mudar o CEP durante a consulta reabilita o botao de busca', async () => {
    const previousDocument = globalThis.document;
    const previousFetch = globalThis.fetch;
    const handlers = {};
    const cep = { value: '01001000', addEventListener: (event, handler) => { handlers[event] = handler; } };
    const button = { disabled: false, addEventListener: (event, handler) => { handlers['button-' + event] = handler; } };
    const status = { textContent: '' };
    const nodes = {
        '[name="postal_code"]': cep,
        '[data-postal-search]': button,
        '[data-postal-status]': status,
        '[name="address_street"]': { value: '' },
        '[name="address_district"]': { value: '' },
        '[name="address_city"]': { value: '' },
        '[name="address_state"]': { value: '' },
    };
    const group = {
        dataset: { postalLookupUrl: '/alunos/cep/__CEP__' },
        querySelector: (selector) => nodes[selector],
    };

    try {
        globalThis.document = { readyState: 'complete', querySelector: () => group };
        globalThis.fetch = () => new Promise(() => {});
        await import('../../public/js/student-postal-code.js?simular-interrupcao=' + Date.now());
        handlers['button-click']();
        assert.equal(button.disabled, true);
        cep.value = '0100';
        handlers.input();
        assert.equal(button.disabled, false);
    } finally {
        globalThis.document = previousDocument;
        globalThis.fetch = previousFetch;
    }
});
