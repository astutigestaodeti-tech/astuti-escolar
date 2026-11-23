const printButton = document.querySelector('[data-action="print-report-card"]');

if (printButton) {
    printButton.addEventListener('click', () => window.print());
}
