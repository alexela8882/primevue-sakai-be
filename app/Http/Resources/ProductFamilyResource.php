<?php

namespace App\Http\Resources;

use App\Models\Product\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductFamilyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {

        $data = (parent::toArray($request));
        $product = Product::find($data['product_id']);
        $inclusiveServices = [];

        if ($product->inclusiveServices) {
            $inclusiveServices = $product->inclusiveServices()->get(['name']);
        }

        return [
            '_id' => $request->_id,
            'price' => $request->price,
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
