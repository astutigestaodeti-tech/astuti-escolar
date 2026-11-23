import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../public/js/declaracao-imprimir.js', import.meta.url), 'utf8');
const css = readFileSync(new URL('../../public/css/astuti.css', import.meta.url), 'utf8');
const view = readFileSync(new URL('../../resources/views/documents/show.blade.php', import.meta.url), 'utf8');

test('impressão da declaração usa botão ligado a arquivo externo pela CSP', () => {
    let click;
    let printed = 0;
    runInNewContext(script, {
        document: { querySelector(selector) {
            assert.equal(selector, '[data-action="print-declaration"]');
            return { addEventListener(type, callback) { assert.equal(type, 'click'); click = callback; } };
        } },
        window: { print() { printed++; } },
    });
    click();
    assert.equal(printed, 1);
    assert.match(view, /<script type="module" src="\{\{ asset\('js\/declaracao-imprimir\.js'\) \}\}"><\/script>/);
    assert.doesNotMatch(view, /onclick\s*=|<script>\s*window\./i);
});

test('layout de impressão mantém página A4 retrato e QR Code local', () => {
    assert.match(css, /@page declaracao-matricula\s*\{\s*size:\s*A4 portrait;/);
    assert.match(css, /@media print\s*\{[\s\S]*?body\.impressao-declaracao \.lateral/);
    assert.match(view, /@section\('body-class', 'impressao-declaracao'\)/);
    assert.match(view, /\{!! \$qrSvg !!\}/);
    assert.match(view, /Assinatura da direção/);
});

test('a impressão da declaração não usa identificadores, saúde ou fotos do estudante', () => {
    for (const secret of ['birth_date', 'cpf', 'ra', 'address_', 'phone', 'guardian', 'photo', 'health', 'race_color']) {
        assert.equal(view.includes('$student->' + secret), false, 'Atributo pessoal indevido: ' + secret);
    }
});
