const printTransportButton = document.querySelector('[data-action="print-transport-routes"]');

if (printTransportButton) {
    printTransportButton.addEventListener('click', () => window.print());
}
