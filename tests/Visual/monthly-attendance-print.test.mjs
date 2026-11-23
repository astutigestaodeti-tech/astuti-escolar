import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const printScript = readFileSync(new URL('../../public/js/monthly-attendance-print.js', import.meta.url), 'utf8');
const styles = readFileSync(new URL('../../public/css/astuti.css', import.meta.url), 'utf8');
const view = readFileSync(new URL('../../resources/views/classes/monthly-attendance-report.blade.php', import.meta.url), 'utf8');

test('botao imprimir chama a impressao do navegador sem JavaScript inline', () => {
    let onClick = null;
    let printed = 0;
    const button = {
        addEventListener(event, handler) {
            assert.equal(event, 'click');
            onClick = handler;
        },
    };
    const document = {
        querySelector(selector) {
            assert.equal(selector, '[data-action="print-monthly-attendance"]');
            return button;
        },
    };
    const window = { print() { printed++; } };

    runInNewContext(printScript, { document, window });
    assert.equal(typeof onClick, 'function');
    onClick();
    assert.equal(printed, 1);
    assert.match(view, /<script type="module" src="\{\{ asset\('js\/monthly-attendance-print\.js'\) \}\}"><\/script>/);
    assert.doesNotMatch(view, /onclick\s*=/i);
});

test('sem botao, o script de impressao nao interfere com outra tela', () => {
    let printed = false;
    runInNewContext(printScript, {
        document: { querySelector() { return null; } },
        window: { print() { printed = true; } },
    });
    assert.equal(printed, false);
});

test('CSS de impressao usa A4 paisagem e oculta apenas elementos da tela de relatorio', () => {
    assert.match(styles, /@page frequencia-mensal\s*\{\s*size:\s*A4 landscape;/);
    assert.match(styles, /@media print\s*\{[\s\S]*body\.impressao-frequencia \.lateral/);
    assert.match(styles, /\.relatorio-mensal-tabela thead\s*\{display:table-header-group\}/);
    assert.match(styles, /\.relatorio-mensal-acoes\s*\{display:none\}/);
    assert.match(view, /@section\('body-class', 'impressao-frequencia'\)/);
    assert.match(view, /Assinatura do professor/);
    assert.match(view, /Assinatura da direção/);
});

test('relatorio impresso nao inclui dados particulares alem de nomes e frequencia', () => {
    for (const forbidden of ['observation', 'correction_reason', 'phone', 'photo_path', 'cpf', 'birth_date', 'race_color']) {
        assert.equal(view.includes(forbidden), false, 'Campo nao permitido: ' + forbidden);
    }
});
