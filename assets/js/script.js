document.addEventListener('DOMContentLoaded', function() {
    var autoDismiss = setTimeout(function() {
        document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
            try { var bs = new bootstrap.Alert(alert); bs.close(); } catch(e) {}
        });
    }, 5000);

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-up');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.scroll-fade').forEach(function(el) { observer.observe(el); });

    function animateCounters() {
        document.querySelectorAll('.counter').forEach(function(el) {
            var target = parseInt(el.getAttribute('data-target')) || parseInt(el.textContent.replace(/[^0-9]/g, '')) || 0;
            if (target === 0) return;
            var duration = 1000, step = Math.ceil(target / (duration / 16)), current = 0;
            var prefix = el.textContent.replace(/[0-9]/g, '');
            var timer = setInterval(function() {
                current += step;
                if (current >= target) { current = target; clearInterval(timer); }
                el.textContent = prefix + current.toLocaleString();
            }, 16);
        });
    }
    var counterObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) { animateCounters(); counterObserver.unobserve(entry.target); }
        });
    }, { threshold: 0.3 });
    document.querySelectorAll('.counter').forEach(function(el) { counterObserver.observe(el); });

    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });

    document.querySelectorAll('.table-hover tbody tr').forEach(function(row) {
        row.addEventListener('click', function() { this.classList.toggle('table-active'); });
    });

    var backToTop = document.createElement('button');
    backToTop.className = 'back-to-top';
    backToTop.innerHTML = '<i class="bi bi-chevron-up"></i>';
    backToTop.setAttribute('aria-label', 'Back to top');
    document.body.appendChild(backToTop);
    window.addEventListener('scroll', function() {
        backToTop.classList.toggle('visible', window.scrollY > 400);
    });
    backToTop.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm') || 'Are you sure?')) e.preventDefault();
        });
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        try { new bootstrap.Tooltip(el); } catch(e) {}
    });

    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var btn = this.querySelector('button[type="submit"]');
            if (btn && !btn.classList.contains('no-loading')) {
                btn.disabled = true;
                btn.classList.add('btn-loading');
            }
        });
    });
});
