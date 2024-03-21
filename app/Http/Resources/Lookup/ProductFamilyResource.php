<?php

namespace App\Http\Resources\Lookup;

use App\Models\Product\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductFamilyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {

        $data = (parent::toArray($request));
        $product = Product::find($data['product_id']);
        $inclusiveServices = [];

        if ($product->inclusiveServices) {
            $inclusiveServices = $product->inclusiveServices()->get(['name']);
        }

        return [
            '_id' => $data['_id'],
            'price' => $data['price'],
            'inclusiveServices' => $inclusiveServices,
            'product_id' => [
                '_id' => $product->_id,
                'name' => $product->name,
                'itemCode' => $product->itemCode,
                'modelCode' => $product->modelCode,
                'product_type_id' => $product->product_type_id ? picklist_value('product_types', $product->product_type_id) : null,
                'description' => $product->description,
            ],
        ];

    }
}
