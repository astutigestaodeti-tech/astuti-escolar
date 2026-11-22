import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatPostalCode, postalCodeDigits, shouldApplySuggestion } from '../../public/js/student-postal-code.js';

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
