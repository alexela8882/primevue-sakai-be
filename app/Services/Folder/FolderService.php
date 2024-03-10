<?php

namespace App\Services\Folder;

use App\Http\Resources\Folder\TopResource;
use App\Models\Core\Folder;
use App\Models\User;

class FolderService
{
    public function getMenuNavigation(User $user)
    {
        $folders = Folder::query()
            ->whereIn('name', ['top', 'admin'])
            ->where('type_id', '5bb104cf678f71061f643c27')
            ->with([
                'modules' => fn ($query) => $query->where('setup', '!=', true)->orderBy('order'),
                'folders',
            ])
            ->get()
            ->map(fn (Folder $folder) => [$folder->name => $folder])
            ->collapse();

        $resource = new TopResource($folders['top']);

        $resource->wrap('top');

        if ($user->roles->contains('crm_admin') && array_key_exists('admin', $folders)) {
            $resource->additional([
                'admin' => new TopResource($folders['admin']),
            ]);
        }

        return $resource;
    }

    public function getByType($type)
    {
        $typeId = picklist_id('folder_types', $type);

        return Folder::where('type_id', $typeId)->get();
    }

    public function getByTypeAndLabel($label, $type)
    {
        $typeId = picklist_id('folder_types', $type);

        return Folder::where(['label' => $label, 'type_id' => $typeId])->first();
    }
}
