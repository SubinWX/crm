{{-- Buttons on one row of a saved list. $id, $editUrl, $code (the finished HTML), $name, $canDelete, $rich (label of the rich copy). --}}
<div class="flex flex-wrap justify-end gap-1" data-html="{{ $code }}"
     x-data="{ done: '',
        flash(k) { this.done = k; setTimeout(() => this.done = '', 1600) },
        async code() { try { await navigator.clipboard.writeText(this.$el.dataset.html) } catch (e) {} this.flash('code') },
        async rich() {
            const html = this.$el.dataset.html, holder = document.createElement('div');
            holder.innerHTML = html;
            try { await navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([html], { type: 'text/html' }), 'text/plain': new Blob([holder.innerText], { type: 'text/plain' }) })]) }
            catch (e) { try { await navigator.clipboard.writeText(html) } catch (e2) {} }
            this.flash('rich');
        } }">
    <a href="{{ $editUrl }}" class="btn-secondary btn-sm">Open</a>
    <button type="button" class="btn-ghost btn-sm" wire:click="preview({{ $id }})">Preview</button>
    <button type="button" class="btn-ghost btn-sm" x-on:click="rich()" x-text="done === 'rich' ? 'Copied' : @js($rich)">{{ $rich }}</button>
    <button type="button" class="btn-ghost btn-sm" x-on:click="code()" x-text="done === 'code' ? 'Copied' : 'Copy HTML'">Copy HTML</button>
    <button type="button" class="btn-ghost btn-sm" wire:click="duplicate({{ $id }})">Duplicate</button>
    @if ($canDelete) <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $id }})" wire:confirm="Delete “{{ $name }}”? This cannot be undone.">Delete</button> @endif
</div>
