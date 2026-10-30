// Cart page: recalculate totals as guest numbers change, and remind the
// customer to save the new numbers before checking out.

(function () {
    var form = document.getElementById('cartForm');
    if (!form) {
        return;
    }

    var freeMin = parseFloat(form.dataset.freeMin);
    var fee = parseFloat(form.dataset.fee);
    var updateButton = document.getElementById('updateCart');

    function formatMoney(amount) {
        return '$' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function recalculate() {
        var subtotal = 0;
        var allValid = true;

        form.querySelectorAll('tr[data-price]').forEach(function (row) {
            var input = row.querySelector('.guests-input');
            var guests = parseInt(input.value, 10);
            var min = parseInt(input.min, 10);
            var max = parseInt(input.max, 10);
            var valid = /^\d+$/.test(input.value.trim()) && guests >= min && guests <= max;

            input.classList.toggle('is-invalid', !valid);
            if (!valid) {
                allValid = false;
                return;
            }
            var lineTotal = parseFloat(row.dataset.price) * guests;
            row.querySelector('.line-total').textContent = formatMoney(lineTotal);
            subtotal += lineTotal;
        });

        if (!allValid) {
            return false;
        }
        var delivery = subtotal >= freeMin ? 0 : fee;
        document.getElementById('cartSubtotal').textContent = formatMoney(subtotal);
        document.getElementById('cartDelivery').textContent = delivery === 0 ? 'Free' : formatMoney(delivery);
        document.getElementById('cartTotal').textContent = formatMoney(subtotal + delivery);
        return true;
    }

    form.addEventListener('input', function (event) {
        if (event.target.classList.contains('guests-input')) {
            recalculate();
            updateButton.classList.add('btn-attention');
            updateButton.textContent = 'Save guest numbers';
        }
    });

    // Block the "update" submit if a guest number is out of range.
    // Remove buttons always go through.
    form.addEventListener('submit', function (event) {
        var submitter = event.submitter;
        if (submitter && submitter.name === 'remove') {
            return;
        }
        if (!recalculate()) {
            event.preventDefault();
            var bad = form.querySelector('.guests-input.is-invalid');
            bad.focus();
            alert('Please check the guest numbers. Each package has a minimum, and the maximum is ' + bad.max + '.');
        }
    });

    // Leaving for checkout with unsaved numbers would check out the old numbers.
    var checkout = form.querySelector('a[href="checkout.php"]');
    if (checkout) {
        checkout.addEventListener('click', function (event) {
            if (updateButton.classList.contains('btn-attention')) {
                event.preventDefault();
                updateButton.focus();
                alert('Please save your new guest numbers first.');
            }
        });
    }
})();
