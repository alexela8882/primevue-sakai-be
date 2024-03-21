<?php

namespace App\Observers\Pricebook;

use App\Models\Core\Log;
use App\Models\Product\Price;

class PriceObserver
{
    public function creating(Price $price)
    {
        if (! isset($price->price)) {
            $price->price = 0;
        }
    }

    public function created(Price $price)
    {
        $collection = collect(['currency_id', 'price', 'temporary_price', 'active', 'pricebook_id', 'product_id'])
            ->map(fn ($field) => [$field => $price->getOriginal($field, null)])
            ->collapse()
            ->put('record_id', $price->_id)
            ->put('entity_id', '5c906a0fa6ebc7193110e7df') // Entity identifier for Price
            ->put('eventHook', 'create')
            ->put('testonly', true)
            ->all();

        Log::create($collection);
    }

    public function updated(Price $price)
    {
        $collection = collect(['currency_id', 'price', 'temporary_price', 'active', 'pricebook_id', 'product_id'])
            ->map(fn ($field) => [$field => $price->{$field}])
            ->collapse()
            ->put('old_price', $price->getOriginal('price'))
            ->put('record_id', $price->_id)
            ->put('entity_id', '5c906a0fa6ebc7193110e7df') // Entity identifier for Price
            ->put('eventHook', 'update')
            ->put('testonly', true)
            ->all();

        Log::create($collection);
    }
}
