import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { formatPostalCode, postalCodeDigits, postalLookupMessage, shouldApplySuggestion } from '../../public/js/student-postal-code.js';

const styles = readFileSync(new URL('../../public/css/astuti.css', import.meta.url), 'utf8');

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
    assert.equal(shouldApplySuggestion('Rua manual', 'Rua manual', 'Rua sugerida', false), false);
    assert.equal(shouldApplySuggestion('', '', 'Rua sugerida', false), true);
    assert.equal(shouldApplySuggestion('Rua manual', 'Rua manual', 'Rua sugerida', true), true);
    assert.equal(shouldApplySuggestion('Rua alterada', 'Rua manual', 'Rua sugerida', true), false);
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

test('dica permanente do CEP tem texto maior e mais contrastante', () => {
    const regra = styles.match(/\.grupo-endereco #postal_code-ajuda\{([^}]+)\}/)?.[1];

    assert.ok(regra, 'A dica do CEP precisa ter estilo próprio');
    assert.match(regra, /color:var\(--tinta-2\)/);
    assert.match(regra, /font-size:14px/);
    assert.match(regra, /font-weight:600/);
});

test('resposta do CEP aparece como orientação azul só quando contém texto', () => {
    const regra = styles.match(/\.grupo-endereco #postal-lookup-status:not\(:empty\)\{([^}]+)\}/)?.[1];

    assert.ok(regra, 'A resposta do CEP precisa ter uma caixa informativa própria');
    assert.match(regra, /background:var\(--caneta-clara\)/);
    assert.match(regra, /color:var\(--caneta-escura\)/);
    assert.match(regra, /border-left:4px solid var\(--caneta\)/);
    assert.match(regra, /font-size:14px/);
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


test('busca automatica preserva dados manuais e busca explicita permite atualizar', async () => {
    const previousDocument = globalThis.document;
    const previousFetch = globalThis.fetch;
    const previousSetTimeout = globalThis.setTimeout;
    const previousClearTimeout = globalThis.clearTimeout;
    const handlers = {};
    let scheduled;
    const cep = { value: '01001000', addEventListener: (event, handler) => { handlers[event] = handler; } };
    const button = { disabled: false, addEventListener: (event, handler) => { handlers[event] = handler; } };
    const status = { textContent: '' };
    const fields = {
        street: { value: 'Rua manual' }, district: { value: 'Bairro manual' },
        city: { value: '' }, state: { value: '' },
    };
    const number = { value: '123' };
    const complement = { value: 'Casa 2' };
    const zone = { value: 'rural' };
    const nodes = {
        '[name="postal_code"]': cep,
        '[data-postal-search]': button,
        '[data-postal-status]': status,
        '[name="address_street"]': fields.street,
        '[name="address_district"]': fields.district,
        '[name="address_city"]': fields.city,
        '[name="address_state"]': fields.state,
        '[name="address_number"]': number,
        '[name="address_complement"]': complement,
        '[name="residence_zone"]': zone,
    };
    try {
        globalThis.document = {
            readyState: 'complete',
            querySelector: () => ({ dataset: { postalLookupUrl: '/alunos/cep/__CEP__' }, querySelector: (selector) => nodes[selector] }),
        };
        globalThis.fetch = async () => ({
            ok: true,
            json: async () => ({ source: 'online', street: 'Rua sugerida', district: 'Centro', city: 'São Paulo', state: 'SP' }),
        });
        await import('../../public/js/student-postal-code.js?simular-preservacao=' + Date.now());
        globalThis.setTimeout = (callback) => { scheduled = callback; return 1; };
        globalThis.clearTimeout = () => {};
        handlers.input();
        await scheduled();
        assert.equal(fields.street.value, 'Rua manual');
        assert.equal(fields.district.value, 'Bairro manual');
        assert.equal(fields.city.value, 'São Paulo');
        assert.equal(fields.state.value, 'SP');
        assert.match(status.textContent, /Campos já preenchidos foram mantidos/);

        await handlers.click();
        assert.equal(fields.street.value, 'Rua sugerida');
        assert.equal(fields.district.value, 'Centro');
        assert.equal(number.value, '123');
        assert.equal(complement.value, 'Casa 2');
        assert.equal(zone.value, 'rural');
        assert.doesNotMatch(status.textContent, /Campos já preenchidos foram mantidos/);
    } finally {
        globalThis.document = previousDocument;
        globalThis.fetch = previousFetch;
        globalThis.setTimeout = previousSetTimeout;
        globalThis.clearTimeout = previousClearTimeout;
    }
});
