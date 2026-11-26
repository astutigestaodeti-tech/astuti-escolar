/* A impressão depende de uma ação explícita do usuário, sem scripts inline. */
const imprimir = document.querySelector('[data-cardapio-imprimir]');
if (imprimir) {
    imprimir.addEventListener('click', () => window.print());
}
