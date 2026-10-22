@php $captcha = \App\Services\Captcha::clientConfig(); @endphp
@if ($captcha)
window.smCaptcha = window.smCaptcha || function (cfg) {
    function renderAll() {
        document.querySelectorAll('.sm-captcha:not([data-rendered])').forEach(function (el) {
            el.setAttribute('data-rendered', '1');
            window[cfg.global].render(el, { sitekey: cfg.sitekey });
        });
    }
    if (window[cfg.global] && window[cfg.global].render) { return renderAll(); }
    window.smCaptchaReady = renderAll;
    if (!document.getElementById('sm-captcha-api')) {
        var s = document.createElement('script');
        s.id = 'sm-captcha-api'; s.async = true; s.src = cfg.script + '?onload=smCaptchaReady&render=explicit';
        document.head.appendChild(s);
    }
};
window.smCaptcha({!! json_encode($captcha, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!});
@endif
