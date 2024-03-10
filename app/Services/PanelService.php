<?php

namespace App\Services;

use App\Models\Core\Panel;

class PanelService
{
    public function getAllPanel($moduleName, $group = 'withMain')
    {
        if ($group == 'withMain') {
            return Panel::where('controllerMethod', $moduleName.'@index')
                ->orWhere(function ($query) use ($moduleName) {
                    $query->where('controllerMethod', $moduleName.'@show')
                        ->where('mutable', true);
                })->get();
        }

        if ($group == 'mainOnly') {
            return Panel::where('controllerMethod', $moduleName.'@index')->get();
        }

        return Panel::where('controllerMethod', $moduleName.'@show')->where('mutable', true)->get();

    }

    public function getPanelField($panelName)
    {
        $panel = Panel::where('name', $panelName)->first();
        $fields = [];
        if ($panel) {
            $fields = array_merge($panel->sections()->pluck('first_ids')->toArray() ?? [], $panel->sections()->pluck('second_ids')->toArray() ?? []);
        }

        return $fields;
    }

    public function deletePanel($entityID, $module = null)
    {
        $panels = Panel::where('entity_id', $entityID);

        if ($module) {
            $panels = $panels->where('controllerMethod', $module.'@show');
        }

        if ($panels) {
            foreach ($panels->get() as $panel) {
                $panel->sections()->delete();
            }

            $panels->delete();
        }
    }

    public function deletePanelByModule($moduleName)
    {
        $panels = Panel::where('controllerMethod', 'like', $moduleName.'%');

        if ($panels) {
            foreach ($panels->get() as $panel) {
                $panel->sections()->delete();
            }

            $panels->delete();
        }

    }

    public function generatePanelName($entityName)
    {
        $cnt = 0;
        $pre = strtolower($entityName).'-panel-';
        do {
            $genName = $pre.++$cnt;
            $check = Panel::where(['name' => $genName]);
        } while ($check);

        return $genName;
    }
}
