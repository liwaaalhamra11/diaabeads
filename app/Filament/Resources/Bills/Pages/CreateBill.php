<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Filament\Resources\Bills\BillResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    protected static bool $canCreateAnother = false;

    /** Bill, its lines and the stock changes are saved together or not at all. */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(fn () => parent::handleRecordCreation($data));
    }

    public function getTitle(): string
    {
        return request()->query('type') === 'out' ? 'New expense' : 'New bill';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
