@php
    /** @var \App\Models\Product $record */
    $record = $getRecord();
    $images = $record->images->map(fn ($image) => $image->url)->values();
@endphp

@if ($images->isEmpty())
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;aspect-ratio:4/3;border:1px dashed #d4d4d4;border-radius:.75rem;color:#737373;text-align:center;padding:1rem;">
        <x-filament::icon icon="heroicon-o-photo" style="width:2.5rem;height:2.5rem;color:#a3a3a3" />
        <span>No photos yet</span>
        @if (\App\Filament\Resources\Products\ProductResource::canEdit($record))
            <x-filament::link :href="\App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $record])" color="bronze">
                Add photos
            </x-filament::link>
        @endif
    </div>
@else
    <div
        x-data="{ active: 0, images: @js($images) }"
        x-on:keydown.arrow-right.window="active = (active + 1) % images.length"
        x-on:keydown.arrow-left.window="active = (active - 1 + images.length) % images.length"
        style="display:flex;flex-direction:column;gap:.75rem;"
    >
        <div style="position:relative;border-radius:.75rem;overflow:hidden;background:#f5f5f5;aspect-ratio:1/1;max-height:560px;margin:0 auto;width:100%;">
            <img
                src="{{ $images->first() }}"
                x-bind:src="images[active]"
                alt="{{ $record->name }}"
                style="width:100%;height:100%;object-fit:contain;display:block;"
            >

            @if ($images->count() > 1)
                <span x-text="`${active + 1} / ${images.length}`"
                      style="position:absolute;right:.75rem;bottom:.75rem;background:rgba(10,10,10,.75);color:#fff;font-size:.75rem;padding:.2rem .55rem;border-radius:999px;"></span>

                <button type="button" x-on:click="active = (active - 1 + images.length) % images.length" aria-label="Previous photo"
                        style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);width:2.25rem;height:2.25rem;border-radius:999px;background:rgba(255,255,255,.9);border:1px solid #e5e5e5;display:grid;place-items:center;cursor:pointer;">
                    <x-filament::icon icon="heroicon-m-chevron-left" style="width:1.25rem;height:1.25rem;color:#0a0a0a" />
                </button>
                <button type="button" x-on:click="active = (active + 1) % images.length" aria-label="Next photo"
                        style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);width:2.25rem;height:2.25rem;border-radius:999px;background:rgba(255,255,255,.9);border:1px solid #e5e5e5;display:grid;place-items:center;cursor:pointer;">
                    <x-filament::icon icon="heroicon-m-chevron-right" style="width:1.25rem;height:1.25rem;color:#0a0a0a" />
                </button>
            @endif
        </div>

        @if ($images->count() > 1)
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                @foreach ($images as $index => $src)
                    <button type="button" x-on:click="active = {{ $index }}" aria-label="Show photo {{ $index + 1 }}"
                            style="width:4.5rem;height:4.5rem;border-radius:.5rem;overflow:hidden;padding:0;cursor:pointer;background:#f5f5f5;border:2px solid transparent;"
                            x-bind:style="{ borderColor: active === {{ $index }} ? '#9b7346' : 'transparent', opacity: active === {{ $index }} ? 1 : .7 }">
                        <img src="{{ $src }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;">
                    </button>
                @endforeach
            </div>
        @endif
    </div>
@endif
