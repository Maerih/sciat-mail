import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

/** Copy text to clipboard with a short "Copied" state. */
Alpine.data('copy', (text) => ({
    copied: false,
    copy(value) {
        navigator.clipboard.writeText(value ?? text);
        this.copied = true;
        setTimeout(() => (this.copied = false), 1500);
    },
}));

/** POST JSON to an AI endpoint; resolves with the JSON body or throws with the server's message. */
async function aiPost(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.message || (res.status === 429 ? 'Too many AI requests — wait a minute.' : 'The AI request failed.'));
    }
    return data;
}

/** Subject line ideas: fills the given subject (and preheader) inputs, or calls onPick(suggestions). */
Alpine.data('aiSubjects', (url, options = {}) => ({
    open: false, loading: false, error: '', suggestions: [],
    async generate() {
        this.open = true; this.loading = true; this.error = '';
        try {
            const subject = options.subjectInput ? document.querySelector(options.subjectInput)?.value : '';
            const data = await aiPost(url, { subject, content: options.content || '', count: options.count || 6 });
            this.suggestions = data.suggestions;
        } catch (e) { this.error = e.message; } finally { this.loading = false; }
    },
    use(s) {
        const set = (sel, val) => { const el = sel && document.querySelector(sel); if (el) { el.value = val; el.dispatchEvent(new Event('input', { bubbles: true })); } };
        set(options.subjectInput, s.subject);
        set(options.preheaderInput, s.preheader);
        this.open = false;
    },
}));

/** Full email draft from a brief; hands the HTML to onDraft(result). */
Alpine.data('aiDraft', (url, onDraft) => ({
    open: false, loading: false, error: '', brief: '', tone: 'friendly', length: 'medium', ctaText: '', ctaUrl: '',
    async generate() {
        this.loading = true; this.error = '';
        try {
            const data = await aiPost(url, { brief: this.brief, tone: this.tone, length: this.length, cta_text: this.ctaText || null, cta_url: this.ctaUrl || null });
            onDraft(data);
            this.open = false;
        } catch (e) { this.error = e.message; } finally { this.loading = false; }
    },
}));

/** Rewrites the text of a textarea in place. */
Alpine.data('aiRewrite', (url, selector) => ({
    loading: false, error: '',
    async rewrite(instruction) {
        const el = document.querySelector(selector);
        if (!el || !el.value.trim()) { this.error = 'Write some text first.'; return; }
        this.loading = true; this.error = '';
        try {
            const data = await aiPost(url, { text: el.value, instruction });
            el.value = data.text;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (e) { this.error = e.message; } finally { this.loading = false; }
    },
}));

window.aiPost = aiPost;

Alpine.start();
