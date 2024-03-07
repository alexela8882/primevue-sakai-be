<?php

namespace App\Http\Resources\Folder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'folders' => FolderResource::collection($this->folders),
            'modules' => $this->modules->pluck('_id'),
        ];
    }
}
