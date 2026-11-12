{{--
    Preview and copy/download buttons shared by the signature and template builders.
    $html is the finished email HTML, $document the full page shown in the preview frame,
    $filename the name used for the download, $richLabel the wording of the main copy button.
    The preview runs in a locked-down frame: no scripts, and the page's own styles cannot leak in.
--}}
<div class="card xl:sticky xl:top-20" data-html="{{ $html }}"
     x-data="{
        mobile: false, done: '',
        html() { return this.$el.dataset.html },
        flash(key) { this.done = key; setTimeout(() => this.done = '', 1800) },
        async copyRich() {
            const frame = this.$refs.frame;
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/html': new Blob([this.html()], { type: 'text/html' }),
                    'text/plain': new Blob([frame.contentDocument.body.innerText], { type: 'text/plain' }),
                })]);
            } catch (e) {
                // Older browsers: select what is shown in the preview and copy that.
                const doc = frame.contentDocument, range = doc.createRange(), selection = frame.contentWindow.getSelection();
                range.selectNodeContents(doc.body); selection.removeAllRanges(); selection.addRange(range);
                doc.execCommand('copy'); selection.removeAllRanges();
            }
            this.flash('rich');
        },
        async copyCode() {
            try { await navigator.clipboard.writeText(this.html()) }
            catch (e) { this.$refs.code.select(); document.execCommand('copy') }
            this.flash('code');
        },
        download() {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob([this.html()], { type: 'text/html' }));
            link.download = @js($filename); link.click(); URL.revokeObjectURL(link.href);
        },
        fit() { const f = this.$refs.frame; if (f && f.contentDocument && f.contentDocument.documentElement) f.style.height = Math.max(120, f.contentDocument.documentElement.scrollHeight + 4) + 'px' },
     }">
    <div class="card-header flex-wrap gap-2">
        <h2 class="card-title">Preview</h2>
        <div class="flex gap-1" role="group" aria-label="Preview size">
            <button type="button" class="btn btn-sm border" :class="!mobile ? 'border-ink-900 bg-ink-900 text-white' : 'border-stone-300 bg-white text-stone-700'" x-on:click="mobile = false; $nextTick(() => fit())">Computer</button>
            <button type="button" class="btn btn-sm border" :class="mobile ? 'border-ink-900 bg-ink-900 text-white' : 'border-stone-300 bg-white text-stone-700'" x-on:click="mobile = true; $nextTick(() => fit())">Phone</button>
        </div>
    </div>
    <div class="overflow-x-auto bg-stone-100 p-3 sm:p-4">
        <iframe x-ref="frame" wire:key="frame-{{ md5($document) }}" title="Preview" sandbox="allow-same-origin" srcdoc="{{ $document }}"
                class="mx-auto block max-w-full rounded-md border border-stone-200 bg-white" :style="{ width: mobile ? '375px' : '100%' }" style="width: 100%; height: 260px;"
                x-on:load="fit(); setTimeout(() => fit(), 400)" x-init="$nextTick(() => fit())"></iframe>
    </div>
    <div class="flex flex-wrap gap-2 border-t border-stone-200 p-4">
        <button type="button" class="btn-primary" x-on:click="copyRich()"><span x-text="done === 'rich' ? 'Copied' : @js($richLabel)">{{ $richLabel }}</span></button>
        <button type="button" class="btn-secondary" x-on:click="copyCode()"><span x-text="done === 'code' ? 'Copied' : 'Copy HTML code'">Copy HTML code</span></button>
        <button type="button" class="btn-secondary" x-on:click="download()"><x-icon name="download" /> Download .html</button>
    </div>
    <details class="border-t border-stone-200">
        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-stone-700 hover:bg-stone-50">View the HTML code</summary>
        <div class="px-4 pb-4">
            <textarea x-ref="code" readonly rows="10" class="input font-mono text-[11px] leading-relaxed" spellcheck="false" aria-label="HTML code" x-on:focus="$el.select()">{{ $html }}</textarea>
        </div>
    </details>
    {{ $slot ?? '' }}
</div>
