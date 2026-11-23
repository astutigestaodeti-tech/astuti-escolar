const printButton = document.querySelector('[data-action="print-monthly-attendance"]');

if (printButton) {
    printButton.addEventListener('click', () => window.print());
}
