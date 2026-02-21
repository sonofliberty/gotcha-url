// Copy-to-clipboard
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-copy');
    if (!btn) return;
    var url = btn.getAttribute('data-url');
    navigator.clipboard.writeText(url).then(function() {
        var original = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.replace('btn-outline-secondary', 'btn-success');
        setTimeout(function() {
            btn.textContent = original;
            btn.classList.replace('btn-success', 'btn-outline-secondary');
        }, 2000);
    });
}, true);

// Inline label editing
document.addEventListener('click', function(e) {
    var el = e.target.closest('.label-display');
    if (!el || el.querySelector('input')) return;

    var linkId = el.getAttribute('data-link-id');
    var currentLabel = el.getAttribute('data-label') || '';

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm';
    input.value = currentLabel;
    input.maxLength = 100;
    input.style.width = '150px';
    input.style.display = 'inline-block';

    el.textContent = '';
    el.appendChild(input);
    input.focus();
    input.select();

    var saved = false;

    function save() {
        if (saved) return;
        saved = true;
        var newLabel = input.value.trim();
        fetch('/dashboard/links/' + linkId + '/label', {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({label: newLabel})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                alert(data.error);
                revert();
                return;
            }
            el.setAttribute('data-label', data.label || '');
            if (data.label) {
                el.textContent = data.label;
            } else {
                el.innerHTML = '<span class="text-muted fst-italic">+ label</span>';
            }
        })
        .catch(function() { revert(); });
    }

    function revert() {
        if (currentLabel) {
            el.textContent = currentLabel;
        } else {
            el.innerHTML = '<span class="text-muted fst-italic">+ label</span>';
        }
    }

    input.addEventListener('keydown', function(ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            save();
        } else if (ev.key === 'Escape') {
            ev.preventDefault();
            saved = true;
            revert();
        }
    });

    input.addEventListener('blur', function() {
        save();
    });
}, true);

// Relative time formatting
(function() {
    var now = Date.now();
    var WEEK = 7 * 24 * 60 * 60 * 1000;
    document.querySelectorAll('time[datetime]').forEach(function(el) {
        var dt = new Date(el.getAttribute('datetime'));
        var diff = now - dt.getTime();
        if (diff < 0 || diff > WEEK) return;
        var seconds = Math.floor(diff / 1000);
        var minutes = Math.floor(seconds / 60);
        var hours = Math.floor(minutes / 60);
        var days = Math.floor(hours / 24);
        var text;
        if (seconds < 60) text = 'just now';
        else if (minutes < 60) text = minutes + 'm ago';
        else if (hours < 24) text = hours + 'h ago';
        else text = days + 'd ago';
        el.textContent = text;
        el.title = el.getAttribute('datetime');
    });
})();
