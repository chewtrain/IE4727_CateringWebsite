// Dish picker on package.php: per-category counters, a cap on how many
// dishes can be ticked, a live price estimate, and checks before submitting.
// PHP repeats every check on the server.

(function () {
    var form = document.getElementById('pickerForm');
    if (!form) {
        return;
    }

    var groups = form.querySelectorAll('.dish-group');
    var guestsInput = document.getElementById('guests');
    var estimate = document.getElementById('estimate');
    var pickedTotal = document.getElementById('pickedTotal');
    var deliveryHint = document.getElementById('deliveryHint');
    var price = parseFloat(form.dataset.price);
    var freeMin = parseFloat(form.dataset.freeMin);
    var fee = parseFloat(form.dataset.fee);

    function formatMoney(amount) {
        return '$' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Show or clear the error message inside a fieldset or field.
    function setError(container, message) {
        var box = container.querySelector('.field-error');
        box.textContent = message;
        box.hidden = message === '';
        container.classList.toggle('has-error', message !== '');
    }

    // Count ticked dishes in a group, and disable the rest once the group is full.
    function updateGroup(group) {
        var limit = parseInt(group.dataset.choose, 10);
        var boxes = group.querySelectorAll('input[type="checkbox"]');
        var count = 0;
        boxes.forEach(function (box) {
            if (box.checked) {
                count++;
            }
        });
        boxes.forEach(function (box) {
            box.disabled = !box.checked && count >= limit;
        });
        group.querySelector('.dish-count').textContent = count;
        group.classList.toggle('is-complete', count === limit);
        if (count === limit) {
            setError(group, '');
        }
        return count;
    }

    function updateTotals() {
        var total = 0;
        groups.forEach(function (group) {
            total += updateGroup(group);
        });
        pickedTotal.textContent = total;

        var guests = parseInt(guestsInput.value, 10);
        if (isNaN(guests) || guests < 0) {
            estimate.textContent = '-';
            return;
        }
        var subtotal = price * guests;
        estimate.textContent = formatMoney(subtotal);
        deliveryHint.textContent = subtotal >= freeMin
            ? 'This order qualifies for free delivery.'
            : 'Add ' + formatMoney(freeMin - subtotal) + ' more for free delivery (otherwise ' + formatMoney(fee) + ').';
    }

    function validateGuests() {
        var min = parseInt(guestsInput.min, 10);
        var max = parseInt(guestsInput.max, 10);
        var value = guestsInput.value.trim();
        var field = guestsInput.closest('.field');

        if (!/^\d+$/.test(value)) {
            setError(field, 'Enter the number of guests as a whole number.');
            return false;
        }
        var guests = parseInt(value, 10);
        if (guests < min || guests > max) {
            setError(field, 'This package needs between ' + min + ' and ' + max + ' guests.');
            return false;
        }
        setError(field, '');
        return true;
    }

    form.addEventListener('change', function (event) {
        if (event.target.type === 'checkbox') {
            updateTotals();
        }
    });

    guestsInput.addEventListener('input', function () {
        updateTotals();
        validateGuests();
    });

    form.addEventListener('submit', function (event) {
        var firstProblem = null;

        if (!validateGuests()) {
            firstProblem = guestsInput;
        }

        groups.forEach(function (group) {
            var limit = parseInt(group.dataset.choose, 10);
            var count = updateGroup(group);
            if (count !== limit) {
                var name = group.querySelector('.dish-group-name').textContent;
                setError(group, 'Choose ' + (limit - count) + ' more from ' + name + '.');
                if (!firstProblem) {
                    firstProblem = group;
                }
            }
        });

        if (firstProblem) {
            event.preventDefault();
            firstProblem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    updateTotals();
})();
