// Shared page behaviour: mobile menu, header border on scroll, scroll reveal.
// Plain JavaScript only (no libraries, no AJAX) per the base version rules.

document.documentElement.classList.add('js');

// Open and close the navigation menu on small screens.
function setupMobileNav() {
    var toggle = document.getElementById('navToggle');
    var links = document.getElementById('navLinks');
    if (!toggle || !links) {
        return;
    }

    toggle.addEventListener('click', function () {
        var isOpen = links.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Close the menu with the Escape key.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && links.classList.contains('open')) {
            links.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });
}

// Show a thin border under the header once the page is scrolled.
function setupHeaderBorder() {
    var header = document.getElementById('siteHeader');
    if (!header) {
        return;
    }

    function update() {
        header.classList.toggle('scrolled', window.scrollY > 10);
    }

    window.addEventListener('scroll', update, { passive: true });
    update();
}

// Fade sections in as they scroll into view.
function setupReveal() {
    var items = document.querySelectorAll('.reveal');

    if (!('IntersectionObserver' in window)) {
        items.forEach(function (item) {
            item.classList.add('visible');
        });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    items.forEach(function (item) {
        observer.observe(item);
    });
}

// Submit a form as soon as a select marked data-autosubmit changes (e.g. sorting).
function setupAutoSubmit() {
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });
}

// Ask before destructive actions. A form or its submit button can carry
// data-confirm="message"; the server still checks everything.
function setupConfirm() {
    document.addEventListener('submit', function (event) {
        var button = event.submitter;
        var message = (button && button.dataset.confirm) || event.target.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
}

setupMobileNav();
setupHeaderBorder();
setupReveal();
setupAutoSubmit();
setupConfirm();
