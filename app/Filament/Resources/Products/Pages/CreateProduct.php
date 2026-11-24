<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\ProductGallery;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<string> */
    protected array $galleryPaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // A disabled subcategory field isn't submitted: that means "none".
        $data['subcategory_id'] ??= null;

        $this->galleryPaths = array_values($data['gallery'] ?? []);
        unset($data['gallery']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        ProductGallery::sync($this->getRecord(), $this->galleryPaths);
    }
}
