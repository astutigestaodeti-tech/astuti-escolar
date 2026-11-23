const printButton = document.querySelector('[data-action="print-declaration"]');

if (printButton) {
    printButton.addEventListener('click', () => window.print());
}
