import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import test from 'node:test';

const cssDir = new URL('../../public/css/', import.meta.url);

test('todos os estilos de página impressa usam A4 e margens institucionais', () => {
    let count = 0;
    for (const file of readdirSync(cssDir).filter(name => name.endsWith('.css'))) {
        const css = readFileSync(new URL(file, cssDir), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
        for (const rule of css.matchAll(/@page\s*([^{}]*)\{([^{}]*)/g)) {
            count++;
            assert.match(rule[2], /\bsize:\s*A4\s+(portrait|landscape)\s*;/, file);
            assert.match(rule[2], /\bmargin:\s*30mm\s+20mm\s+20mm\s+30mm\s*(?:;|$)/, file);
            assert.doesNotMatch(rule[2], /\bmargin-(?:top|right|bottom|left)\s*:/, file);
        }
    }
    assert.ok(count >= 9, 'As regras de página existentes precisam ser verificadas.');
});

test('estilo principal oferece A4 retrato como fallback e mantém frequência em paisagem', () => {
    const css = readFileSync(new URL('astuti.css', cssDir), 'utf8');
    assert.match(css, /@page\s*\{\s*size:A4 portrait;\s*margin:30mm 20mm 20mm 30mm/);
    assert.match(css, /@page frequencia-mensal\s*\{\s*size:A4 landscape;/);
});
