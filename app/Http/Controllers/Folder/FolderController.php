<?php

namespace App\Http\Controllers\Folder;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Folder\FolderService;
use App\Services\ModuleDataCollector;

class FolderController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector, private User $user, private FolderService $folderService)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser();
    }

    public function getMenuNavigation()
    {
        return $this->folderService->getMenuNavigation($this->moduleDataCollector->user);
    }
}
