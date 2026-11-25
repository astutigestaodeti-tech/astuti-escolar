'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const purpose = document.getElementById('ficha-print-finalidade');
    if (!purpose) return;

    const boxes = Array.from(document.querySelectorAll('[data-print-sensitive]'));
    const update = () => {
        purpose.required = boxes.some((box) => box.checked);
        if (!purpose.required) purpose.value = '';
    };
    boxes.forEach((box) => box.addEventListener('change', update));
    update();
});
