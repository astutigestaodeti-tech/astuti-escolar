(() => {
    'use strict';
    const content = document.getElementById('agente-dados');
    const status = document.getElementById('agente-atualizacao');
    if (!content || !status) return;
    let busy = false;
    let stopped = false;
    let previous = null;
    async function refresh() {
        if (busy || stopped || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(content.dataset.endpoint, {
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'error',
                headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(7000),
            });
            if (response.status === 401 || response.status === 403) {
                content.replaceChildren();
                status.textContent = 'Acesso expirado ou revogado. Reabra a página para confirmar o acesso.';
                stopped = true;
                return;
            }
            if (!response.ok) throw new Error('snapshot');
            const data = await response.json();
            if (typeof data.html !== 'string' || typeof data.stale !== 'boolean' || typeof data.available !== 'boolean') throw new Error('snapshot');
            // Fragmento produzido exclusivamente pelo Blade autenticado com dados sanitizados.
            if (data.html !== previous && !content.contains(document.activeElement)) {
                content.innerHTML = data.html;
                previous = data.html;
            }
            status.textContent = !data.available ? 'Monitoramento indisponível.' : data.stale ? 'Coleta desatualizada.' : 'Consulta atualizada.';
        } catch {
            status.textContent = 'Falha ao consultar. O histórico exibido não representa necessariamente o estado atual.';
        } finally {
            busy = false;
        }
    }
    window.setInterval(refresh, 8000);
    document.addEventListener('visibilitychange', refresh);
})();
