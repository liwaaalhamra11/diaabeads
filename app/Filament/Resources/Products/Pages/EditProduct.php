<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\ProductGallery;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<string> */
    protected array $galleryPaths = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->color('gray'),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['gallery'] = $this->getRecord()->images()->pluck('path')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A disabled subcategory field isn't submitted: that means "none".
        $data['subcategory_id'] ??= null;

        $this->galleryPaths = array_values($data['gallery'] ?? []);
        unset($data['gallery']);

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterSave(): void
    {
        ProductGallery::sync($this->getRecord(), $this->galleryPaths);
    }
}
