<x-layouts.app title="AI assistant">
    <x-settings-frame tab="ai">
    <p class="-mt-2 text-sm text-[var(--muted)]">Subject line ideas, rewrites and email drafts inside campaigns and automations. One engine serves every client.</p>
    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('platform.ai.update') }}" class="card card-body space-y-4 xl:col-span-2" x-data="{ provider: @js(old('ai_provider', $provider ?? '')) }">
            @csrf @method('PUT')
            <div>
                <label class="label">Engine</label>
                <select name="ai_provider" class="input" x-model="provider">
                    <option value="">Off</option>
                    @foreach (\App\Services\Ai\AiAssistant::PROVIDERS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div x-show="provider === 'anthropic' || provider === 'openai'" x-cloak>
                <x-field name="ai_api_key" type="password" label="API key" autocomplete="off" :placeholder="$hasKey ? '•••••••• (saved — leave blank to keep)' : ''" />
            </div>
            <div x-show="provider" x-cloak>
                <x-field name="ai_model" label="Model" :value="$model" ::placeholder="provider === 'anthropic' ? '{{ \App\Services\Ai\AnthropicEngine::DEFAULT_MODEL }} (default)' : (provider === 'ollama' ? 'e.g. llama3.1' : 'model name from your OpenAI account')" />
                <p class="help" x-show="provider === 'anthropic'">Leave empty to use Claude Opus 5.5. Declined requests are retried automatically on Anthropic's recommended fallback model.</p>
            </div>
            <div x-show="provider === 'ollama' || provider === 'openai'" x-cloak>
                <x-field name="ai_base_url" type="url" label="Server URL" :value="$baseUrl" ::placeholder="provider === 'ollama' ? 'http://localhost:11434' : 'https://api.openai.com/v1'" />
                <p class="help" x-show="provider === 'ollama'">With Ollama, prompts and email content never leave your own server.</p>
            </div>
            <div class="flex justify-end"><button class="btn-primary">Save</button></div>
        </form>
        <div class="card card-body space-y-3 text-sm text-gray-600">
            <h2 class="card-title">Last 30 days</h2>
            <div class="flex justify-between"><span>Requests</span><span class="font-semibold tabular-nums">{{ number_format($usage->total ?? 0) }}</span></div>
            <div class="flex justify-between"><span>Failed</span><span class="tabular-nums">{{ number_format($usage->failed ?? 0) }}</span></div>
            <p class="help border-t border-gray-100 pt-3">Email content and briefs are sent to the selected engine when someone uses an AI feature. Nothing is sent otherwise.</p>
        </div>
    </div>
    </x-settings-frame>
</x-layouts.app>
