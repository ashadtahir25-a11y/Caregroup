/* CARE Group - shared interface script
   Page transitions, parallax, 3D tilt, counters, scroll reveal,
   toasts, password tools and the mobile menu. No libraries needed. */
(function () {
    'use strict';

    var body = document.body;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var finePointer = window.matchMedia('(pointer: fine)').matches;

    /* ---------- Page transitions ---------- */
    window.setTimeout(function () { body.classList.remove('is-entering'); }, 700);

    // Coming back with the browser "Back" button restores a cached page: clear the overlay.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { body.classList.remove('is-leaving', 'is-entering'); }
    });

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link || reduceMotion) return;
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download') || link.dataset.noTransition !== undefined) return;

        var dest = new URL(link.href, window.location.href);
        if (dest.origin !== window.location.origin) return;
        // Same page, only the #section changes: let the browser scroll smoothly.
        if (dest.pathname === window.location.pathname && dest.search === window.location.search && dest.hash) return;

        e.preventDefault();
        body.classList.add('is-leaving');
        window.setTimeout(function () { window.location.href = dest.href; }, 480);
    });

    /* ---------- Navigation: glass on scroll + mobile menu ---------- */
    var nav = document.querySelector('[data-nav]');
    if (nav) {
        var onScroll = function () { nav.classList.toggle('is-scrolled', window.scrollY > 12); };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        var toggle = nav.querySelector('[data-nav-toggle]');
        var menu = document.getElementById('nav-menu');
        if (toggle && menu) {
            toggle.addEventListener('click', function () {
                var open = menu.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            menu.addEventListener('click', function (e) {
                if (e.target.closest('a')) {
                    menu.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }

    /* ---------- Parallax: background layers follow the mouse and scroll ---------- */
    var layers = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
    if (layers.length && !reduceMotion) {
        var mx = 0, my = 0, ticking = false;
        var paint = function () {
            var sy = window.scrollY;
            layers.forEach(function (el) {
                var s = parseFloat(el.dataset.parallax) || 0;
                el.style.setProperty('--px', (mx * s).toFixed(1) + 'px');
                el.style.setProperty('--py', (my * s - sy * s * 0.01).toFixed(1) + 'px');
            });
            ticking = false;
        };
        var request = function () { if (!ticking) { ticking = true; window.requestAnimationFrame(paint); } };
        if (finePointer) {
            window.addEventListener('mousemove', function (e) {
                mx = e.clientX / window.innerWidth - 0.5;
                my = e.clientY / window.innerHeight - 0.5;
                request();
            }, { passive: true });
        }
        window.addEventListener('scroll', request, { passive: true });
    }

    /* ---------- 3D tilt cards with moving glare ---------- */
    if (finePointer && !reduceMotion) {
        document.querySelectorAll('[data-tilt]').forEach(function (card) {
            var max = parseFloat(card.dataset.tilt) || 8;
            card.addEventListener('pointermove', function (e) {
                var r = card.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width;
                var y = (e.clientY - r.top) / r.height;
                card.classList.add('is-tilting');
                card.style.setProperty('--ry', ((x - 0.5) * max * 2).toFixed(2) + 'deg');
                card.style.setProperty('--rx', ((0.5 - y) * max * 2).toFixed(2) + 'deg');
                card.style.setProperty('--gx', (x * 100).toFixed(1) + '%');
                card.style.setProperty('--gy', (y * 100).toFixed(1) + '%');
            });
            card.addEventListener('pointerleave', function () {
                card.classList.remove('is-tilting');
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
            });
        });
    }

    /* ---------- Scroll reveal + animated counters ---------- */
    var countUp = function (el) {
        var target = parseFloat(el.dataset.count) || 0;
        var suffix = el.dataset.suffix || '';
        if (reduceMotion) { el.textContent = target.toLocaleString() + suffix; return; }
        var start = null, duration = 1600;
        var step = function (t) {
            if (!start) start = t;
            var p = Math.min((t - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 4);
            el.textContent = Math.round(target * eased).toLocaleString() + suffix;
            if (p < 1) window.requestAnimationFrame(step);
        };
        window.requestAnimationFrame(step);
    };

    var watched = document.querySelectorAll('[data-reveal], [data-count]');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                if (el.hasAttribute('data-reveal')) el.classList.add('is-visible');
                if (el.hasAttribute('data-count')) countUp(el);
                io.unobserve(el);
            });
        }, { threshold: 0.2 });
        watched.forEach(function (el) { io.observe(el); });
    } else {
        watched.forEach(function (el) {
            el.classList.add('is-visible');
            if (el.hasAttribute('data-count')) el.textContent = el.dataset.count + (el.dataset.suffix || '');
        });
    }

    /* ---------- Toasts ---------- */
    var hideToast = function (toast) {
        if (!toast || toast.classList.contains('is-hiding')) return;
        toast.classList.add('is-hiding');
        window.setTimeout(function () { toast.remove(); }, 360);
    };
    document.querySelectorAll('[data-toast]').forEach(function (toast, i) {
        window.setTimeout(function () { hideToast(toast); }, 5000 + i * 600);
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toast-close]');
        if (btn) hideToast(btn.closest('[data-toast]'));
    });

    /* ---------- Password: show/hide + strength meter ---------- */
    document.querySelectorAll('[data-reveal-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('aria-controls'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.querySelector('i').className = 'fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye');
        });
    });

    document.querySelectorAll('[data-strength-for]').forEach(function (meter) {
        var input = document.getElementById(meter.dataset.strengthFor);
        var label = document.getElementById(meter.dataset.strengthLabel);
        var words = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        if (!input) return;
        input.addEventListener('input', function () {
            var v = input.value, score = 0;
            if (v.length >= 8) score++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) score++;
            if (!v) score = 0;
            meter.dataset.level = String(score);
            if (label) label.textContent = v ? 'Password strength: ' + words[score || 1] : 'Use at least 8 characters with a letter and a number.';
        });
    });

    /* ---------- Buttons show a spinner while the form submits ---------- */
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.classList.add('is-loading'); btn.setAttribute('aria-busy', 'true'); }
        });
    });
})();
