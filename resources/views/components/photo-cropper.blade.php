{{--
    Choose a picture, then crop it to a square before it is uploaded.
    Usage: <x-photo-cropper model="photo" label="Headshot" />

    Nothing is uploaded when the file is chosen. The picture opens in a round frame: drag it to move it,
    use the slider (or the mouse wheel) to zoom, then "Use this crop" uploads the 600 × 600 result to the
    Livewire property named by "model". The crop is done in the browser, so a large original is fine.
--}}
@props(['model' => 'photo', 'label' => 'Picture'])
<div wire:ignore.self {{ $attributes }}
     x-data="{
        src: null, w: 0, h: 0, zoom: 1, x: 0, y: 0, frame: 256, drag: null, busy: false, error: '',
        pick(e) {
            const file = e.target.files[0];
            e.target.value = '';
            this.error = '';
            if (! file) return;
            if (! file.type.startsWith('image/')) { this.error = 'Choose a picture file (JPG, PNG or WebP).'; return; }
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                if (img.naturalWidth < 100 || img.naturalHeight < 100) { this.error = 'That picture is too small. Choose one at least 100 pixels wide and tall.'; URL.revokeObjectURL(url); return; }
                this.w = img.naturalWidth; this.h = img.naturalHeight; this.zoom = 1; this.src = url;
                this.$nextTick(() => { this.frame = this.$refs.frame.clientWidth || 256; this.x = (this.frame - this.w * this.scale()) / 2; this.y = (this.frame - this.h * this.scale()) / 2; });
            };
            img.onerror = () => { this.error = 'That file could not be opened as a picture.'; URL.revokeObjectURL(url); };
            img.src = url;
        },
        scale() { return (this.frame / Math.min(this.w, this.h)) * this.zoom; },
        clamp() {
            const s = this.scale();
            this.x = Math.min(0, Math.max(this.frame - this.w * s, this.x));
            this.y = Math.min(0, Math.max(this.frame - this.h * s, this.y));
        },
        setZoom(z) {
            // Zoom around the middle of the frame, so the picture does not jump.
            const before = this.scale(), c = this.frame / 2;
            const px = (c - this.x) / before, py = (c - this.y) / before;
            this.zoom = Math.min(4, Math.max(1, z));
            const after = this.scale();
            this.x = c - px * after; this.y = c - py * after;
            this.clamp();
        },
        start(e) { this.drag = { px: e.clientX, py: e.clientY, x: this.x, y: this.y }; e.currentTarget.setPointerCapture(e.pointerId); },
        move(e) { if (! this.drag) return; this.x = this.drag.x + e.clientX - this.drag.px; this.y = this.drag.y + e.clientY - this.drag.py; this.clamp(); },
        nudge(dx, dy) { this.x += dx; this.y += dy; this.clamp(); },
        cancel() { if (this.src) URL.revokeObjectURL(this.src); this.src = null; this.busy = false; },
        apply() {
            const s = this.scale(), size = 600;
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = size;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, size, size);
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(this.$refs.img, -this.x / s, -this.y / s, this.frame / s, this.frame / s, 0, 0, size, size);
            this.busy = true;
            canvas.toBlob(blob => {
                if (! blob) { this.busy = false; this.error = 'The picture could not be cropped. Try another one.'; return; }
                $wire.upload(@js($model), new File([blob], 'photo.jpg', { type: 'image/jpeg' }),
                    () => this.cancel(),
                    () => { this.busy = false; this.error = 'The upload failed. Check the connection and try again.'; });
            }, 'image/jpeg', 0.9);
        },
     }">
    <label class="btn-secondary cursor-pointer" x-show="! src">
        <x-icon name="photo" /> Choose a picture
        <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" @change="pick($event)" aria-label="{{ $label }}">
    </label>
    <p x-show="error" x-cloak x-text="error" class="field-error"></p>

    <div x-show="src" x-cloak class="mt-1 rounded-md border border-stone-200 bg-stone-50 p-4">
        <p class="mb-3 text-sm font-semibold text-stone-900">Crop the picture</p>
        <div class="flex flex-wrap items-start gap-5">
            <div x-ref="frame" class="relative size-56 shrink-0 cursor-grab touch-none overflow-hidden rounded-full border-2 border-white bg-stone-200 shadow ring-1 ring-stone-300 select-none active:cursor-grabbing sm:size-64"
                 tabindex="0" role="application" aria-label="Crop area. Drag the picture, or use the arrow keys, to move it."
                 @pointerdown.prevent="start($event)" @pointermove="move($event)" @pointerup="drag = null" @pointercancel="drag = null"
                 @wheel.prevent="setZoom(zoom - $event.deltaY * 0.002)"
                 @keydown.left.prevent="nudge(10, 0)" @keydown.right.prevent="nudge(-10, 0)" @keydown.up.prevent="nudge(0, 10)" @keydown.down.prevent="nudge(0, -10)">
                <img x-ref="img" :src="src" alt="" draggable="false" class="pointer-events-none absolute top-0 left-0 max-w-none"
                     :style="`width: ${w * scale()}px; height: ${h * scale()}px; transform: translate(${x}px, ${y}px);`">
            </div>
            <div class="min-w-48 flex-1 space-y-3">
                <p class="text-sm text-stone-600">Drag the picture to move it. Use the slider to zoom in or out. What is inside the circle is what gets saved.</p>
                <label class="block text-xs font-semibold tracking-wide text-stone-600 uppercase">Zoom
                    <input type="range" min="1" max="4" step="0.01" class="mt-1 block w-full" :value="zoom" @input="setZoom(parseFloat($event.target.value))">
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-primary" @click="apply()" :disabled="busy"><span x-show="! busy">Use this crop</span><span x-show="busy">Uploading…</span></button>
                    <button type="button" class="btn-secondary" @click="cancel()" :disabled="busy">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
