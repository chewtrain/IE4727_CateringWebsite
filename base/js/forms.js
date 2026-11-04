// Client-side validation for forms marked with data-validate
// (checkout, feedback, order tracking, staff login).
// HTML5 attributes cover the basics; this adds friendly messages and the
// rules HTML5 cannot express: matching emails, Singapore postal sectors,
// the delivery date window and character counters. PHP re-checks everything.

(function () {
    var forms = document.querySelectorAll('form[data-validate]');

    function errorBox(field) {
        var wrapper = field.closest('.field');
        return wrapper ? wrapper.querySelector('.field-error') : null;
    }

    function showError(field, message) {
        var wrapper = field.closest('.field');
        var box = errorBox(field);
        if (box) {
            box.textContent = message;
            box.hidden = message === '';
        }
        if (wrapper) {
            wrapper.classList.toggle('has-error', message !== '');
        }
        field.setAttribute('aria-invalid', message !== '' ? 'true' : 'false');
        if (box && box.id) {
            field.setAttribute('aria-describedby', box.id);
        }
    }

    // Singapore postal sectors are 01-82, except 74.
    function validPostalSector(code) {
        var sector = parseInt(code.substring(0, 2), 10);
        return sector >= 1 && sector <= 82 && sector !== 74;
    }

    // Return an error message for one field, or '' if it is fine.
    function checkField(field, form) {
        var value = field.type === 'checkbox' || field.type === 'radio' ? '' : field.value.trim();
        var label = field.closest('.field') ? field.closest('.field').querySelector('label, legend') : null;
        var name = label ? label.textContent.replace('(optional)', '').trim() : 'This field';

        if (field.type === 'radio') {
            var group = form.querySelectorAll('input[name="' + field.name + '"]');
            var picked = Array.prototype.some.call(group, function (radio) { return radio.checked; });
            return field.required && !picked ? 'Please choose one option.' : '';
        }
        if (field.type === 'checkbox') {
            return field.required && !field.checked ? 'Please tick this box to continue.' : '';
        }
        if (field.required && value === '') {
            return name + ' is required.';
        }
        if (value === '') {
            return '';
        }
        if (field.minLength > 0 && value.length < field.minLength) {
            return name + ' must be at least ' + field.minLength + ' characters.';
        }
        if (field.type === 'email' && !/^[^\s@]+@(?:[^\s@]+\.[^\s@]{2,}|localhost)$/i.test(value)) {
            return 'Enter a valid email address, like name@example.com.';
        }
        if (field.dataset.match) {
            var other = form.querySelector('#' + field.dataset.match);
            var same = field.type === 'password'
                ? other && other.value === field.value
                : other && other.value.trim().toLowerCase() === value.toLowerCase();
            if (!same) {
                return field.type === 'password' ? 'The two passwords do not match.' : 'The two email addresses do not match.';
            }
        }
        if (field.type === 'tel' && !/^[689]\d{7}$/.test(value.replace(/\s/g, ''))) {
            return 'Enter an 8-digit Singapore number starting with 6, 8 or 9.';
        }
        if (field.hasAttribute('data-postal')) {
            if (!/^\d{6}$/.test(value)) {
                return 'Postal codes have exactly 6 digits.';
            }
            if (!validPostalSector(value)) {
                return 'That is not a Singapore postal code. Please check the first two digits.';
            }
        }
        if (field.type === 'date') {
            if (form.dataset.earliest && value < form.dataset.earliest) {
                return 'We need at least 3 days to prepare. Choose ' + form.dataset.earliest + ' or later.';
            }
            if (form.dataset.latest && value > form.dataset.latest) {
                return 'We take bookings up to 6 months ahead. Choose an earlier date.';
            }
        }
        if (field.pattern && !new RegExp('^(?:' + field.pattern + ')$').test(value)) {
            return field.dataset.patternMessage || 'Please check the format of ' + name.toLowerCase() + '.';
        }
        return '';
    }

    function setupCounter(textarea) {
        var counter = textarea.closest('.field').querySelector('.char-count');
        function update() {
            counter.textContent = textarea.value.length;
        }
        textarea.addEventListener('input', update);
        update();
    }

    forms.forEach(function (form) {
        var fields = form.querySelectorAll('input:not([type="hidden"]), select, textarea');

        // Check a field when the user leaves it, then live once it has an error.
        fields.forEach(function (field) {
            field.addEventListener('blur', function () {
                if (field.type !== 'radio') {
                    showError(field, checkField(field, form));
                }
            });
            field.addEventListener('input', function () {
                if (field.getAttribute('aria-invalid') === 'true') {
                    showError(field, checkField(field, form));
                }
            });
            field.addEventListener('change', function () {
                if (field.type === 'radio' || field.type === 'checkbox') {
                    showError(field, checkField(field, form));
                }
            });
        });

        form.querySelectorAll('textarea[data-counter]').forEach(setupCounter);

        form.addEventListener('submit', function (event) {
            var firstBad = null;
            var checkedGroups = {};

            fields.forEach(function (field) {
                if (field.type === 'radio') {
                    if (checkedGroups[field.name]) {
                        return;
                    }
                    checkedGroups[field.name] = true;
                }
                var message = checkField(field, form);
                showError(field, message);
                if (message && !firstBad) {
                    firstBad = field;
                }
            });

            if (firstBad) {
                event.preventDefault();
                firstBad.focus();
            }
        });
    });
})();
