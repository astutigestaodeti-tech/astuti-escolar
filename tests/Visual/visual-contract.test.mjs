import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';

const text = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const exists = (path) => existsSync(new URL(`../../${path}`, import.meta.url));
const luminance = (hex) => {
    const channels = [1, 3, 5].map((start) => Number.parseInt(hex.slice(start, start + 2), 16) / 255);
    const linear = channels.map((value) => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
};
const contrast = (first, second) => {
    const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);
    return (lighter + 0.05) / (darker + 0.05);
};

test('o sistema hospeda suas fontes e a identidade visual sem CDN', () => {
    const css = text('public/css/astuti.css');
    assert.match(css, /Atkinson Hyperlegible/);
    assert.match(css, /Source Serif 4/);
    assert.doesNotMatch(css, /@import\s+url\(['"]?https?:/);
    assert.ok(exists('public/fonts/LICENSE-Atkinson-Hyperlegible.txt'));
    assert.ok(exists('public/fonts/LICENSE-Source-Serif-4.txt'));
    assert.ok(exists('public/fonts/atkinson-hyperlegible-latin-400-normal.woff2'));
    assert.ok(exists('public/fonts/source-serif-4-latin-wght-normal.woff2'));
});

test('as telas partilham estilos cacheáveis com versão atualizável', () => {
    for (const page of [
        'resources/views/welcome.blade.php',
        'resources/views/auth/login.blade.php',
        'resources/views/layouts/municipal.blade.php',
        'resources/views/demo/painel.blade.php',
    ]) {
        const blade = text(page);
        assert.ok(blade.includes("asset('css/astuti.css')"), page);
        assert.ok(blade.includes("filemtime(public_path('css/astuti.css'))"), page);
    }
});

test('indicador de foco fica visível também em fundo claro', () => {
    const css = text('public/css/astuti.css');
    assert.match(css, /:focus-visible\s*\{\s*outline:3px solid var\(--caneta\)/);
    assert.match(css, /\.lateral :focus-visible[^}]+var\(--ouro-vivo\)/);
    assert.match(css, /\.entrada:focus-visible\s*\{\s*outline:3px solid var\(--caneta\)/);

    const color = (name) => {
        const result = css.match(new RegExp(`--${name}:(#[a-fA-F0-9]{6})`));
        assert.ok(result, `Token de cor ${name} não encontrado`);
        return result[1];
    };

    assert.ok(contrast(color('caneta'), color('folha')) >= 3, 'O foco azul precisa contrastar no fundo branco');
    assert.ok(contrast(color('ouro-vivo'), color('noite')) >= 3, 'O foco dourado precisa contrastar no menu');
});

test('a página inicial não faz consultas diretas ao banco em Blade', () => {
    const blade = text('resources/views/welcome.blade.php');
    assert.ok(!blade.includes('@php'), 'A consulta de municípios deve ficar no HomeController');
    assert.ok(!blade.includes('Membership::query()'), 'A view não deve fazer consultas');
});
