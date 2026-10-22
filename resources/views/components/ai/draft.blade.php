@props(['onDraft'])
@if (\App\Services\Ai\AiAssistant::isConfigured())
<div x-data="aiDraft(@js(route('ai.draft')), {{ $onDraft }})">
    <button type="button" class="btn-secondary btn-sm" @click="open = true"><x-icon name="bolt" class="size-4" /> Write with AI</button>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto overlay p-4 sm:p-10" @keydown.escape.window="open = false">
        <div class="card w-full max-w-xl" @click.outside="open = false">
            <div class="card-header"><h2 class="card-title">Draft this email with AI</h2><button type="button" class="btn-ghost btn-sm" @click="open = false"><x-icon name="x" class="size-4" /></button></div>
            <div class="card-body space-y-3">
                <div><label class="label">What is the email about?</label>
                    <textarea x-model="brief" rows="5" class="input" placeholder="e.g. Announce our autumn sale: 20% off all coats until Sunday, free delivery over $50, link to the sale page."></textarea></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label">Tone</label><select x-model="tone" class="input"><option value="friendly">Friendly</option><option value="professional">Professional</option><option value="playful">Playful</option><option value="urgent">Urgent</option><option value="inspirational">Inspirational</option></select></div>
                    <div><label class="label">Length</label><select x-model="length" class="input"><option value="short">Short</option><option value="medium">Medium</option><option value="long">Long</option></select></div>
                    <div><label class="label">Button text (optional)</label><input x-model="ctaText" class="input" placeholder="Shop the sale"></div>
                    <div><label class="label">Button link (optional)</label><input x-model="ctaUrl" type="url" class="input" placeholder="https://"></div>
                </div>
                <p x-show="error" class="error" x-text="error"></p>
                <p class="help">The draft replaces the current content. Review it before sending — AI can make mistakes.</p>
                <div class="flex justify-end"><button type="button" class="btn-primary" @click="generate()" :disabled="loading || brief.trim().length < 10" x-text="loading ? 'Writing…' : 'Generate draft'"></button></div>
            </div>
        </div>
    </div>
</div>
@endif
