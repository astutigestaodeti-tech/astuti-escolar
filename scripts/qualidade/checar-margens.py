"""
Confere se campos, botões, textos e caixas ficam no recuo interno dos cartões (.folha).

Uso:
  mkdir -p /tmp/telas
  ASTUTI_DUMP_TELAS=/tmp/telas php artisan test --filter=AllSchoolPagesOpenTest
  python3 scripts/qualidade/checar-margens.py /tmp/telas 1440 390

Requer Python com Playwright e Chromium. Sai com código 1 se achar algo a menos de 12 px da borda.
Tabelas e listas de borda a borda são permitidas.
"""
import glob, sys
from playwright.sync_api import sync_playwright
JS = r"""
() => {
  const out = [];
  const tableish = el => el.closest('table, .rolagem, .alerta-faltas-rolagem, thead, tbody, tr, td, th');
  document.querySelectorAll('.folha').forEach(folha => {
    const f = folha.getBoundingClientRect();
    if (f.width < 50) return;
    folha.querySelectorAll('*').forEach(el => {
      if (tableish(el) || el.closest('.sr-only')) return;
      const r = el.getBoundingClientRect();
      if (r.width === 0 || r.height === 0) return;
      const cs = getComputedStyle(el);
      if (cs.visibility === 'hidden' || cs.display === 'none') return;
      if (el.closest('details:not([open]) > :not(summary)')) return;
      const control = el.matches('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), select, textarea, button, a.botao');
      const boxed = (parseFloat(cs.borderLeftWidth) > 0 || parseFloat(cs.borderRightWidth) > 0 ||
        (cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== getComputedStyle(folha).backgroundColor)) && !el.matches('span, abbr, small, strong, a, img, svg, path, .etiqueta *, .foto-id *');
      const hasText = cs.textAlign !== 'center' && [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1) && cs.display !== 'inline';
      if (!(control || boxed || hasText)) return;
      const left = Math.round(r.left - f.left), right = Math.round(f.right - r.right);
      const min = (control || boxed) ? Math.min(left, right) : left;
      if (min < 12) {
        const p = el.parentElement;
        out.push({el: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).join('.') : ''),
                  pai: p ? p.tagName.toLowerCase() + (typeof p.className === 'string' && p.className ? '.' + p.className.trim().split(/\s+/).join('.') : '') : '',
                  folha: folha.className, left, right, txt: (el.innerText || el.value || '').trim().slice(0, 40)});
      }
    });
  });
  return out;
}
"""
pasta = sys.argv[1] if len(sys.argv) > 1 else '/tmp/telas'
widths = [int(w) for w in sys.argv[2:]] or [1440, 390]
with sync_playwright() as p:
    b = p.chromium.launch()
    total = 0
    for path in sorted(glob.glob(pasta.rstrip('/') + '/*.html')):
        name = path.split('/')[-1][:-5]
        for w in widths:
            pg = b.new_page(viewport={'width': w, 'height': 900})
            pg.goto('file://' + path); pg.wait_for_timeout(150)
            res = pg.evaluate(JS)
            seen = set()
            for r in res:
                key = (r['el'], r['folha'])
                if key in seen: continue
                seen.add(key); total += 1
                print(f"{name}@{w}: {r['el']} (pai {r['pai']}) em [{r['folha']}] esq={r['left']} dir={r['right']} '{r['txt']}'")
            pg.close()
    print('Problemas de margem:', total)
    b.close()
    sys.exit(1 if total else 0)
