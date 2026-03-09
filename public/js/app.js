// Theme switcher
(function() {
    function getPreferredTheme() {
        return localStorage.getItem('gotcha-theme') || 'auto';
    }

    function getResolvedTheme(pref) {
        if (pref === 'auto') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return pref;
    }

    function applyTheme(pref) {
        var resolved = getResolvedTheme(pref);
        document.documentElement.setAttribute('data-bs-theme', resolved);

        // Update button icon visibility
        document.querySelectorAll('.theme-icon-light, .theme-icon-dark, .theme-icon-auto').forEach(function(el) {
            el.classList.add('d-none');
        });
        var activeIcon = document.querySelector('.theme-icon-' + pref);
        if (activeIcon) activeIcon.classList.remove('d-none');

        // Mark active dropdown item
        document.querySelectorAll('[data-theme-value]').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-theme-value') === pref);
        });
    }

    // Apply on load
    applyTheme(getPreferredTheme());

    // Click handler for theme buttons
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-theme-value]');
        if (!btn) return;
        var value = btn.getAttribute('data-theme-value');
        localStorage.setItem('gotcha-theme', value);
        applyTheme(value);
    });

    // Re-apply when OS preference changes (for auto mode)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
        applyTheme(getPreferredTheme());
    });
})();

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

// Link type toggle (create form)
(function() {
    var radios = document.querySelectorAll('input[name="link_type_radio"]');
    if (!radios.length) return;

    var hiddenInput = document.getElementById('link-type-input');
    var redirectFields = document.getElementById('redirect-fields');
    var pageFields = document.getElementById('page-fields');
    var urlInput = document.getElementById('target-url-input');
    var contentInput = document.getElementById('markdown-content-input');

    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            var isPage = this.value === 'page';
            hiddenInput.value = this.value;
            redirectFields.classList.toggle('d-none', isPage);
            pageFields.classList.toggle('d-none', !isPage);
            urlInput.required = !isPage;
            contentInput.required = isPage;
        });
    });
})();

// Content editor (link detail page)
(function() {
    var editBtn = document.getElementById('edit-content-btn');
    if (!editBtn) return;

    var display = document.getElementById('content-display');
    var editor = document.getElementById('content-editor');
    var textarea = document.getElementById('content-textarea');
    var saveBtn = document.getElementById('save-content-btn');
    var cancelBtn = document.getElementById('cancel-content-btn');
    var linkId = editBtn.getAttribute('data-link-id');

    editBtn.addEventListener('click', function() {
        display.classList.add('d-none');
        editBtn.classList.add('d-none');
        editor.classList.remove('d-none');
        textarea.focus();
    });

    cancelBtn.addEventListener('click', function() {
        editor.classList.add('d-none');
        display.classList.remove('d-none');
        editBtn.classList.remove('d-none');
        textarea.value = display.textContent.trim();
    });

    saveBtn.addEventListener('click', function() {
        saveBtn.disabled = true;
        fetch('/dashboard/links/' + linkId + '/content', {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({markdown_content: textarea.value})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            saveBtn.disabled = false;
            if (data.error) {
                alert(data.error);
                return;
            }
            display.textContent = data.markdown_content;
            editor.classList.add('d-none');
            display.classList.remove('d-none');
            editBtn.classList.remove('d-none');
        })
        .catch(function() {
            saveBtn.disabled = false;
            alert('Failed to save content.');
        });
    });
})();

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
