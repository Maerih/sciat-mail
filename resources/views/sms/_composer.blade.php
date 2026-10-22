{{-- SMS textarea with a live character/segment counter (GSM-7 vs Unicode) and merge tags --}}
@php $suffix = $suffix ?? ''; @endphp
<div x-data="{
        text: @js($value ?? ''),
        suffix: @js($suffix),
        gsm: /^[@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !&quot;#¤%&'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\\[~\]|€]*$/,
        get full() { return this.text + (this.suffix ? '\n' + this.suffix : '') },
        get unicode() { return !this.gsm.test(this.full) },
        get length() { return this.unicode ? [...this.full].length : this.full.length + (this.full.match(/[\^{}\\\[~\]|€]/g) || []).length },
        get parts() { const [one, many] = this.unicode ? [70, 67] : [160, 153]; return this.length <= one ? 1 : Math.ceil(this.length / many) },
        insert(tag) { const el = this.$refs.sms; const s = el.selectionStart; const v = '{'.repeat(2) + ' ' + tag + ' ' + '}'.repeat(2); el.setRangeText(v, s, el.selectionEnd, 'end'); this.text = el.value; el.focus() }
    }" @sms-suffix.window="suffix = $event.detail">
    <div class="mb-1.5 flex items-center justify-between">
        <label class="label !mb-0" for="f-{{ $name }}">Message</label>
        <div class="relative" x-data="{ open: false }">
            <button type="button" class="btn-ghost btn-sm" @click="open = !open"><x-icon name="data_object" class="size-4" /> Merge tags</button>
            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-20 mt-1 max-h-72 w-64 overflow-y-auto rounded-lg border border-[var(--line)] bg-[var(--surface)] p-1 shadow-lg">
                @foreach ($tags as $tag => $label)
                    @continue(str_ends_with($tag, '_URL') || in_array($tag, ['RSS_ITEMS', 'SIGNATURE', 'CONTACT_ADDRESS'], true))
                    <button type="button" class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-[var(--surface-2)]" @click="insert(@js($tag)); open = false">{{ $label }} <code class="block text-xs text-[var(--muted)]">{{ $tag }}</code></button>
                @endforeach
            </div>
        </div>
    </div>
    <textarea id="f-{{ $name }}" name="{{ $name }}" x-ref="sms" x-model="text" rows="5" maxlength="1600" class="input" placeholder="Hi &#123;&#123; SUBSCRIBER_FIRST_NAME | there &#125;&#125;, …"></textarea>
    @error($name)<p class="error">{{ $message }}</p>@enderror
    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-xs text-[var(--muted)]">
        <span><span x-text="length"></span> characters · <span class="font-semibold text-[var(--text)]" x-text="parts"></span> <span x-text="parts === 1 ? 'SMS' : 'SMS parts'"></span> per recipient <span x-show="unicode" x-cloak class="badge-yellow ml-1">Unicode — 70 chars per SMS</span></span>
        <span>Merge tags are replaced per contact, so lengths can vary.</span>
    </div>
</div>
