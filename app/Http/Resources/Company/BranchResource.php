<?php

namespace App\Http\Resources\Company;

use App\Http\Resources\Core\CountryResource;
use App\Models\Core\Picklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $x = $this->timezone_id;
        if ($x) {
            $picklists = new Picklist();
        }
        $x = $picklists->getItemById('timezone', $x)->toArray()['value'];

        return [
            '_id' => $this->_id,
            'country_id' => ['_id' => $this->country_id, 'name' => $this->whenLoaded('country') ? $this->country->name : null],
            // 'country_id' => CountryResource::make($this->country->name),
            'name' => $this->name,
            'timezone_id' => $x,
            'currency_id' => null,
            'pricebook_id' => null,
        ];

    }
}
