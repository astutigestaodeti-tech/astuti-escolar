import test from 'node:test';
import assert from 'node:assert/strict';
import { formatGuardianPhone, maskGuardianPhoneField } from '../../public/js/guardian-phone.js';

test('telefone celular é exibido com DDD, cinco dígitos e hífen', () => {
    assert.equal(formatGuardianPhone('11987654321'), '(11) 98765-4321');
    assert.equal(formatGuardianPhone('(11) 98765-4321'), '(11) 98765-4321');
});

test('telefone fixo com dez dígitos é formatado', () => {
    assert.equal(formatGuardianPhone('1133334444'), '(11) 3333-4444');
});

test('máscara aceita digitação parcial e limita a onze números', () => {
    assert.equal(formatGuardianPhone('1'), '(1');
    assert.equal(formatGuardianPhone('1198'), '(11) 98');
    assert.equal(formatGuardianPhone('11987654321999'), '(11) 98765-4321');
});

test('campo do formulário recebe máscara ao carregar e ao digitar', () => {
    const listeners = {};
    const input = {
        value: '11987654321',
        addEventListener(event, listener) { listeners[event] = listener; },
    };
    maskGuardianPhoneField(input);
    assert.equal(input.value, '(11) 98765-4321');
    input.value = '1133334444';
    listeners.input();
    assert.equal(input.value, '(11) 3333-4444');
});
