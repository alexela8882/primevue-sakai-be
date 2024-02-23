<?php

namespace App\Http\Resources\Folder;

use App\Http\Resources\Core\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'order' => $this->order,
            'icon' => $this->icon,
            'folders' => FolderResource::collection($this->folders),
            'modules' => ModuleResource::collection($this->modules),
        ];
    }
}
