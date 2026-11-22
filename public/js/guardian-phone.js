export function formatGuardianPhone(value) {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 11);
    if (!digits) return '';
    if (digits.length <= 2) return `(${digits}`;

    const ddd = digits.slice(0, 2);
    const number = digits.slice(2);
    if (number.length <= 4) return `(${ddd}) ${number}`;

    const prefixLength = number.length > 8 ? 5 : 4;
    return `(${ddd}) ${number.slice(0, prefixLength)}-${number.slice(prefixLength)}`;
}

export function maskGuardianPhoneField(input) {
    input.value = formatGuardianPhone(input.value);
    input.addEventListener('input', () => {
        input.value = formatGuardianPhone(input.value);
    });
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-guardian-phone]').forEach(maskGuardianPhoneField);
}
