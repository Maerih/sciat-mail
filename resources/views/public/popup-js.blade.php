(function () {
    var cfg = {!! json_encode([
        'uid' => $form->uid, 'title' => $form->title, 'description' => $form->popup('description'), 'trigger' => $form->popup('trigger'),
        'delay' => (int) $form->popup('delay'), 'scroll' => (int) $form->popup('scroll'), 'position' => $form->popup('position'),
        'days' => (int) $form->popup('frequency_days'), 'color' => $form->popup('color'), 'action' => route('subscribe.store', $form->uid),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
    var html = {!! json_encode($html, JSON_HEX_TAG) !!};
    var key = 'sm-popup-' + cfg.uid;
    var preview = window.smPopupPreview === true || /[?&]sm_preview=1/.test(location.search);

    try {
        var last = localStorage.getItem(key);
        if (!preview && last && cfg.days > 0 && Date.now() - parseInt(last, 10) < cfg.days * 864e5) { return; }
    } catch (e) {}

    var shown = false;
    function show() {
        if (shown || window.__smPopupOpen) { return; }
        shown = true;
        window.__smPopupOpen = true;
        try { localStorage.setItem(key, String(Date.now())); } catch (e) {}

        var bar = cfg.position === 'top-bar' || cfg.position === 'bottom-bar';
        var root = document.createElement('div');
        root.id = 'sm-popup-root';
        var css = '#sm-popup-root .sm-box{position:fixed;z-index:2147483646;background:#fff;color:#1f2937;box-shadow:0 20px 50px rgba(0,0,0,.25);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;box-sizing:border-box}'
            + '#sm-popup-root .sm-overlay{position:fixed;inset:0;background:rgba(17,24,39,.55);z-index:2147483645}'
            + '#sm-popup-root .sm-close{position:absolute;top:8px;right:12px;border:0;background:none;font-size:24px;line-height:1;cursor:pointer;color:#6b7280}'
            + '#sm-popup-root .sm-form{max-width:none}#sm-popup-root .sm-form h3{display:none}#sm-popup-root .sm-form button{background:' + cfg.color + '}'
            + '#sm-popup-root h2{margin:0 0 6px;font-size:22px}#sm-popup-root .sm-desc{margin:0 0 14px;font-size:14px;color:#4b5563}#sm-popup-root .sm-msg{font-size:15px;padding:8px 0}';
        var pos = {
            'center': 'top:50%;left:50%;transform:translate(-50%,-50%);width:min(440px,92vw);max-height:90vh;overflow:auto;border-radius:16px;padding:28px',
            'slide-right': 'right:20px;bottom:20px;width:min(360px,92vw);border-radius:14px;padding:22px',
            'bottom-bar': 'left:0;right:0;bottom:0;padding:16px 48px 16px 20px',
            'top-bar': 'left:0;right:0;top:0;padding:16px 48px 16px 20px'
        }[cfg.position] || '';
        root.innerHTML = '<style>' + css + (bar ? '#sm-popup-root .sm-form{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;max-width:960px;margin:0 auto}#sm-popup-root .sm-row{margin:0;flex:1 1 180px}#sm-popup-root .sm-form button{width:auto}' : '') + '</style>'
            + (cfg.position === 'center' ? '<div class="sm-overlay"></div>' : '')
            + '<div class="sm-box" role="dialog" aria-modal="true" style="' + pos + '"><button class="sm-close" aria-label="Close">&times;</button>'
            + (cfg.title && !bar ? '<h2></h2>' : '') + (cfg.description && !bar ? '<p class="sm-desc"></p>' : '') + html + '</div>';
        if (cfg.title && !bar) { root.querySelector('h2').textContent = cfg.title; }
        if (cfg.description && !bar) { root.querySelector('.sm-desc').textContent = cfg.description; }
        document.body.appendChild(root);

        function close() { root.remove(); window.__smPopupOpen = false; }
        root.querySelector('.sm-close').addEventListener('click', close);
        var overlay = root.querySelector('.sm-overlay');
        if (overlay) { overlay.addEventListener('click', close); }

        var form = root.querySelector('form');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button[type=submit]');
            btn.disabled = true;
            fetch(cfg.action, { method: 'POST', body: new URLSearchParams(new FormData(form)), headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok) {
                        form.outerHTML = '<div class="sm-msg"></div>';
                        root.querySelector('.sm-msg').textContent = d.message;
                        try { localStorage.setItem(key, String(Date.now() + 3650 * 864e5)); } catch (e) {}
                        setTimeout(close, 4000);
                    } else {
                        btn.disabled = false;
                        var first = d.errors ? Object.values(d.errors)[0] : null;
                        alert(first ? first[0] : 'Please check the form.');
                    }
                })
                .catch(function () { btn.disabled = false; alert('Something went wrong. Please try again.'); });
        });
    }

    function arm() {
        if (preview || cfg.trigger === 'immediate') { return show(); }
        if (cfg.trigger === 'delay') { return setTimeout(show, cfg.delay * 1000); }
        if (cfg.trigger === 'scroll') {
            window.addEventListener('scroll', function onScroll() {
                var h = document.documentElement;
                if ((h.scrollTop + window.innerHeight) / h.scrollHeight * 100 >= cfg.scroll) { window.removeEventListener('scroll', onScroll); show(); }
            }, { passive: true });
        }
        if (cfg.trigger === 'exit') {
            document.addEventListener('mouseout', function onOut(e) {
                if (!e.relatedTarget && e.clientY <= 0) { document.removeEventListener('mouseout', onOut); show(); }
            });
        }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', arm); } else { arm(); }
})();
