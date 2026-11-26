import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../public/js/cardapio-impressao.js', import.meta.url), 'utf8');
const view = readFileSync(new URL('../../resources/views/meals/menu.blade.php', import.meta.url), 'utf8');

test('impressão do cardápio responde ao clique sem impressão automática', () => {
    let callback;
    let prints = 0;
    runInNewContext(script, {
        document: { querySelector: selector => {
            assert.equal(selector, '[data-cardapio-imprimir]');
            return { addEventListener: (event, fn) => {
                assert.equal(event, 'click');
                callback = fn;
            } };
        } },
        window: { print: () => { prints++; } },
    });
    assert.equal(prints, 0);
    assert.equal(typeof callback, 'function');
    callback();
    assert.equal(prints, 1);
});

test('script tolera ausência do botão e prévia possui controle de impressão', () => {
    assert.doesNotThrow(() => runInNewContext(script, { document: { querySelector: () => null } }));
    assert.ok(view.includes("request()->boolean('imprimir')"));
    assert.ok(view.includes('data-cardapio-imprimir'));
    assert.ok(view.includes('cardapio-impressao.js'));
    assert.ok(view.includes('@if($canEdit && !$imprimir)'));
});
