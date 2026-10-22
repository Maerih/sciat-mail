@php
    $siteConfig = json_encode([
        'collect' => route('site.collect', $website->uid),
        'track' => $website->track_visits,
        'inline' => $inline,
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
@endphp
(function () {
    if (window.__smSiteLoaded) { return; }
    window.__smSiteLoaded = true;
    var cfg = {!! $siteConfig !!};

    function store(key, value) {
        try { if (value === undefined) { return localStorage.getItem(key); } localStorage.setItem(key, value); } catch (e) { return null; }
    }

    // Visitors arriving from a tracked email link carry ?_sm=..., which identifies the contact
    var params = new URLSearchParams(location.search);
    if (params.get('_sm')) {
        store('sm-id', params.get('_sm'));
        params.delete('_sm');
        var clean = location.pathname + (params.toString() ? '?' + params.toString() : '') + location.hash;
        try { history.replaceState(null, '', clean); } catch (e) {}
    }
    var visitor = store('sm-v');
    if (!visitor) { visitor = Math.random().toString(36).slice(2) + Date.now().toString(36); store('sm-v', visitor); }

    var payload = JSON.stringify({ v: visitor, id: store('sm-id') || '', p: location.pathname + location.search, r: document.referrer });
    if (navigator.sendBeacon) {
        navigator.sendBeacon(cfg.collect, new Blob([payload], { type: 'text/plain' }));
    } else {
        fetch(cfg.collect, { method: 'POST', body: payload, keepalive: true, mode: 'no-cors' });
    }

    // Inline forms: <div data-sm-form="FORM_ID"></div> anywhere on the page
    function mountInline() {
        document.querySelectorAll('[data-sm-form]').forEach(function (el) {
            var form = cfg.inline[el.getAttribute('data-sm-form')];
            if (!form || el.getAttribute('data-sm-mounted')) { return; }
            el.setAttribute('data-sm-mounted', '1');
            el.innerHTML = form.html;
            var node = el.querySelector('form');
            node.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = node.querySelector('button[type=submit]');
                if (btn) { btn.disabled = true; }
                fetch(form.action, { method: 'POST', body: new URLSearchParams(new FormData(node)), headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.ok) {
                            var msg = document.createElement('div');
                            msg.className = 'sm-msg';
                            msg.style.cssText = 'padding:12px 0;font:15px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif';
                            msg.textContent = d.message;
                            node.replaceWith(msg);
                        } else {
                            if (btn) { btn.disabled = false; }
                            var first = d.errors ? Object.values(d.errors)[0] : null;
                            alert(first ? first[0] : 'Please check the form.');
                        }
                    })
                    .catch(function () { if (btn) { btn.disabled = false; } alert('Something went wrong. Please try again.'); });
            });
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', mountInline); } else { mountInline(); }
})();
@foreach ($popups as $form)
@include('public.popup-js', ['form' => $form, 'html' => view('public.partials.form-embed', ['form' => $form])->render()])
@endforeach
