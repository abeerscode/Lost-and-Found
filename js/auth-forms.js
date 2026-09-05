(function () {
    'use strict';

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        var targetId = button.getAttribute('data-password-toggle');
        var input = document.getElementById(targetId);
        if (!input) return;

        button.addEventListener('click', function () {
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            button.classList.toggle('is-visible', isHidden);
            input.focus({ preventScroll: true });
        });
    });

    var personType = document.getElementById('person_type');
    var batchField = document.getElementById('batch-field');
    var batchInput = document.getElementById('batch');

    function syncBatchField() {
        if (!personType || !batchField || !batchInput) return;
        var isStudent = personType.value === 'student';
        batchField.hidden = !isStudent;
        batchInput.disabled = !isStudent;
        batchInput.required = isStudent;
        if (!isStudent) batchInput.value = '';
    }

    if (personType) {
        personType.addEventListener('change', syncBatchField);
        syncBatchField();
    }
}());
