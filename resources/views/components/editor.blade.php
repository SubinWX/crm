{{--
    Formatted-text field: bold, italic, headings, bullet and numbered lists, links.
    Usage: <x-editor model="form.description" :value="$form['description']" />

    The editor manages its own content, so Livewire leaves it alone (wire:ignore) and each change
    is sent with the next request. The key includes the content, so when the server changes the
    text (a different record opened, a section moved, the form reset) a fresh editor is drawn.
--}}
@props(['model', 'value' => '', 'placeholder' => '', 'minHeight' => '8rem', 'label' => 'Text'])
@php $id = 'editor-'.substr(md5($model.'|'.(string) $value), 0, 16); @endphp
<div wire:key="{{ $id }}" wire:ignore {{ $attributes->class('rich-editor') }}
     x-data x-on:trix-change="$wire.set(@js($model), $event.target.inputElement ? $event.target.inputElement.value : $event.target.value, false)" x-on:trix-file-accept.prevent x-on:trix-attachment-add.prevent>
    <input type="hidden" id="{{ $id }}" value="{{ \App\Support\RichText::forEditor($value) }}">
    <trix-editor input="{{ $id }}" class="rich" style="min-height: {{ $minHeight }};" placeholder="{{ $placeholder }}" aria-label="{{ $label }}"></trix-editor>
</div>
