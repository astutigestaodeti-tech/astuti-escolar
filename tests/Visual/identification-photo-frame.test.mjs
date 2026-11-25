import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readFileSync(new URL('../../public/css/astuti.css', import.meta.url), 'utf8');
const print = readFileSync(new URL('../../public/css/ficha-impressao.css', import.meta.url), 'utf8');

test('fotografias de identificação têm área útil 3:4 sem borda interna', () => {
    assert.match(css, /\.foto-id-imagem,\.foto-id-vazia\{[^}]*object-fit:contain/);
    const frame = css.match(/\.foto-id \.foto-id-imagem\{([^}]+)\}/)?.[1] ?? '';
    assert.match(frame, /(?:^|;)border:0(?:;|$)/);
    assert.match(frame, /outline:1px solid var\(--pauta\)/);
    assert.match(frame, /border-radius:0/);
    assert.match(frame, /object-position:center/);
    for (const [size, width, height] of [['pequena', 33, 44], ['media', 54, 72], ['grande', 90, 120]]) {
        const rule = css.match(new RegExp('\\.foto-id-' + size + ' \\.foto-id-imagem,\\.foto-id-' + size + ' \\.foto-id-vazia\\{([^}]+)\\}'))?.[1] ?? '';
        assert.ok(rule.includes('width:' + width + 'px;height:' + height + 'px'), size);
        assert.equal(width * 4, height * 3);
    }
});

test('moldura impressa mantém área 30 por 40 mm e encaixe integral', () => {
    const frame = print.match(/\.ficha-print-foto\{([^}]+)\}/)?.[1] ?? '';
    assert.match(frame, /width:30mm;height:40mm/);
    assert.match(frame, /(?:^|;)border:0(?:;|$)/);
    assert.match(frame, /outline:1px solid #c5ced9/);
    assert.match(print, /\.ficha-print-foto img\{[^}]*width:100%;height:100%;object-fit:contain/);
    assert.match(print, /@page ficha-cadastral\{size:A4 portrait;margin:30mm 20mm 20mm 30mm/);
});

test('ficha compacta mantém margens, conteúdo visível e fonte legível na impressão', () => {
    assert.match(print, /body\.impressao-ficha-aluno \.ficha-print-papel\{font:9\.5pt\/1\.15 Arial,sans-serif\}/);
    assert.match(print, /\.ficha-print-dados\{grid-template-columns:repeat\(3,minmax\(0,1fr\)\)/);
    assert.match(print, /\.ficha-print-identidade \.ficha-print-dados\{grid-template-columns:repeat\(2,minmax\(0,1fr\)\)\}/);
    assert.match(print, /body\.impressao-ficha-aluno \.ficha-print-papel\{[^}]*overflow:visible/);
    assert.doesNotMatch(print, /(?:line-clamp\s*:|text-overflow\s*:|zoom\s*:|transform\s*:\s*scale)/);
});

test('fundo da página impressa da ficha é branco inclusive abaixo do rodapé', () => {
    const printRules = print.slice(print.indexOf('@media print{'));
    assert.match(printRules, /:root:has\(body\.impressao-ficha-aluno\)\{background:#fff\}/);
    assert.match(printRules, /body\.impressao-ficha-aluno\{[^}]*background:#fff/);
});
