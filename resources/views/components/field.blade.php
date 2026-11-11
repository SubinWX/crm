@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false])
<div {{ $attributes }}>
    @if ($label)
        <label class="label">{{ $label }} @if ($required)<span class="text-red-600" aria-hidden="true">*</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($hint) <p class="mt-1 text-xs text-stone-500">{{ $hint }}</p> @endif
    @if ($name) @error($name) <p class="field-error">{{ $message }}</p> @enderror @endif
</div>
