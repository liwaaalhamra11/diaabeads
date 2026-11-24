<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        /** @var Product $product */
        $product = $this->getRecord();

        return collect([
            $product->sku,
            collect([$product->category?->name, $product->subcategory?->name])->filter()->join(' › ') ?: null,
            $product->trashed() ? 'In trash' : null,
        ])->filter()->join('  ·  ');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('togglePublish')
                ->label(fn (Product $record) => $record->is_published ? 'Move to draft' : 'Publish')
                ->icon(fn (Product $record) => $record->is_published ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
                ->color('gray')
                ->authorize('update')
                ->hidden(fn (Product $record) => $record->trashed())
                ->action(function (Product $record) {
                    $record->update(['is_published' => ! $record->is_published]);
                })
                ->successNotificationTitle(fn (Product $record) => $record->is_published ? 'Product published' : 'Product moved to draft'),
            EditAction::make()
                ->icon(Heroicon::OutlinedPencilSquare),
            ActionGroup::make([
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ]),
        ];
    }
}
