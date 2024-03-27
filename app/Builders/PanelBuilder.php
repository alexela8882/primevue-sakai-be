<?php

namespace App\Builders;

use App\Models\Core\Entity;
use App\Models\Core\Panel;
use App\Models\Core\Section;
use App\Models\Module\Module;
use App\Services\EntityService;
use App\Services\PanelService;

class PanelBuilder
{
    protected $orderCnt;

    protected $sectionList;

    protected $fieldList;

    protected $panel;

    protected $entity;

    protected $mainEntity;

    protected $moduleName;

    protected $sectionName;

    protected $page;

    public function __construct(private PanelService $panelService)
    {
    }

    public function deletePanel($entity, $onModule = null)
    {
        $entity = Entity::where('name', $entity)->first();
        if ($entity) {
            return $this->panelService->deletePanel($entity->_id, $onModule);
        }
    }

    public function deletePanelByModule($onModule)
    {
        return $this->panelService->deletePanelByModule($onModule);
    }

    public function on($moduleName)
    {
        $this->mainEntity = Module::where('name', $moduleName)->first()->entity;
        $this->entity = null;
        $this->panel = null;
        $this->sectionList = null;
        $this->orderCnt = 1;
        $this->moduleName = $moduleName;
        $this->fieldList = null;
        $this->sectionName = null;
        $this->page = null;

        return $this;
    }

    public function atIndex($order)
    {
        $this->entity = $this->mainEntity;
        $this->setPanel($this->moduleName.'@index', $order);

        $this->page = 'index';

        return $this;
    }

    public function atShow($entity, $order, $fkOrSf = null, $mutable = false, $paginated = 50, $defineFk = null)
    {
        $this->page = 'show';
        if ($mutable) {
            $con = $this->mainEntity->deepConnectedEntities()->where('name', $entity)->first();
            if ($con) {
                $this->entity = $con;

                $this->setPanel($this->moduleName.'@show', $order, true, $paginated);

                if ($fkOrSf) {
                    if (count($fkOrSf) != 2) {
                        throw new \Exception('Error. Invalid selection field value.');
                    }

                    $this->panel['selectionField'] = $fkOrSf;
                }

                if ($defineFk) {
                    $fk = Entity::where('name', $entity)->first()->fields()->where('name', $defineFk)->first();
                    if (! $fk) {
                        throw new \Exception('Error. Invalid foreign key field value.');
                    }

                    $this->panel['foreignKey'] = $fk->_id;
                }

                if ($fkOrSf) {
                    $this->panel['mutableType'] = 'withSelection';
                } else {
                    $this->panel['mutableType'] = 'withForm';
                }
            } else {
                throw new \Exception('Error. '.$entity.' is not a connected entity of '.$this->mainEntity->name.'.');
            }
        } else {
            $con = $this->mainEntity->connectedEntities()->where('name', $entity)->first();
            if ($con) {
                if (! $fkOrSf) {
                    $fkOrSf = idify(strtolower($this->mainEntity->name));
                } elseif (is_array($fkOrSf)) {
                    throw new \Exception('Error. Invalid foreignKey value.');
                }

                $lookupModels = (new EntityService)->getLookUpFields($this->mainEntity->name, $entity);

                if (! $lookupModels) {
                    throw new \Exception('Error. No lookupModel found in entity '.$entity.' with '.$this->moduleName.' main entity as its related entity.');
                }

                if (! in_array($fkOrSf, $lookupModels->pluck('name')->toArray())) {
                    throw new \Exception('Error. Unable to find lookupModel field : '.$fkOrSf.' in entity '.$entity.'.');
                }

                $displayFieldName = $lookupModels->where('name', $fkOrSf)->first()->relation->displayFieldName;

                $this->entity = $con;
                $this->setPanel($this->moduleName.'@show', $order, false);
                $this->panel['foreignKey'] = $fkOrSf;
                $this->panel['displayFieldName'] = $displayFieldName;
            } else {
                throw new \Exception('Error. '.$entity.' is not a connected entity of '.$this->mainEntity->name.'.');
            }
        }

        return $this;
    }

    private function setPanel($controllerMethod, $order, $mutable = true, $paginated = true)
    {

        $panelName = $this->panelService->generatePanelName($this->entity->name);
        $this->panel = [
            'name' => $panelName,
            'order' => $order,
            'entity_id' => $this->entity->_id,
            'mutable' => $mutable,
            'tabKey' => 'N',
            'paginated' => $paginated,
            'controllerMethod' => $controllerMethod, //moduleName@index or moduleName@show
        ];
    }

    public function addSection($label, $fields)
    {
        if (! $fields) {
            throw new \Exception('Section must have atleast one field.');
        }

        if (array_depth($fields) != 2) {
            throw new \Exception('Invalid parameter for addField method.');
        }

        $this->sectionName = $this->generateSectionName();
        $this->sectionList[] = ['name' => $this->sectionName, 'label' => $label, 'row' => $this->orderCnt];

        $entityfields = Entity::where('name', $this->entity->name)->first();

        foreach ($fields as $key => $col) {

            $kId = $entityfields->fields()->whereIn('name', $col)->pluck('_id')->toArray();

            if (! $kId) {
                continue;
            }

            $this->fieldList[$this->sectionName][] = $kId;
        }

        $this->orderCnt++;

        return $this;
    }

    public function addButton($label, $link)
    {
        if ($this->page != 'show') {
            throw new \Exception('Error. AddButton function is only applicable for mutable and connected entity panels.');
        }
        if (! $label || ! $link) {
            throw new \Exception('Error. AddButton function must have a label and route.');
        }
        $this->panel['buttons'][] = ['label' => $label, 'route' => $link];

        return $this;
    }

    public function required()
    {
        $this->panel['required'] = true;

        return $this;
    }

    public function addQuery($query)
    {
        $this->panel['filterQuery'] = $query;

        return $this;
    }

    public function tabKey($tabKey)
    {
        if ($tabKey == 'N' || $tabKey == 'Z') {
            $this->panel['tabKey'] = $tabKey;
        }

        return $this;
    }

    public function isChild($parentEntity)
    {
        if (! $parentEntity) {
            throw new \Exception("Error. Please specify panel's parent entity.");
        }

        if ($this->page != 'show') {
            throw new \Exception('Error. isChild attribute is only applicable for connected entity panels.');
        }

        $parent = $this->mainEntity->deepConnectedEntities()->where('name', $parentEntity)->first();

        if (! $parent) {
            throw new \Exception('Error. Invalid parent entity for .'.$this->entity->name.' panel.');
        }

        $child = $parent->connectedEntities()->where('name', $this->entity->name)->first();

        if (! $child) {
            throw new \Exception('Error.'.$this->entity->name.' is not a connected entity of .'.$parentEntity.' entity.');
        }

        $this->panel['isChild'] = true;
        $this->panel['parentEntity'] = $parentEntity;

        return $this;
    }

    public function onTab($tabName)
    {
        $this->panel['tabName'] = $tabName;

        return $this;
    }

    public function compute($fieldName, $method)
    {
        $this->panel['compute'] = ['fieldName' => $fieldName, 'method' => $method];

        return $this;
    }

    public function highlight($value, $color)
    {
        if (! in_array($color, ['red', 'green', 'blue', 'yellow'])) {
            throw new \Exception('Error. Invalid parameter for panel highlight method.');
        }
        $this->panel['highlight'] = ['value' => $value, 'color' => $color];

        return $this;
    }

    public function alignment($alignment = 'justify')
    {
        if (! in_array($alignment, ['justify', 'center', 'left', 'right'])) {
            throw new \Exception('Error. Invalid parameter for alignment method.');
        }

        $this->panel['alignment'] = $alignment;

        return $this;
    }

    public function inLine()
    {
        if ($this->page == 'show' && $this->panel['mutable'] && ! ($this->panel['selectionField'])) {
            $this->panel['mutableType'] = 'inLine';
        } else {
            throw new \Exception('Error. inLine method is only applicable for mutable panels without selection field.');
        }

        return $this;
    }

    public function withOption($entityName, $parent = false)
    {
        if ($this->page == 'show' && $this->panel['mutable']) {
            if ($entityName) {
                $entity = Entity::where('name', $entityName)->first();
                if ($entity) {
                    $panel = Panel::where('controllerMethod', $this->moduleName.'@show')
                        ->where('mutable', true)
                        ->where('entity_id', $entity->_id)
                        ->count();

                    if ($panel) {
                        $this->panel['mutableType'] = 'withOption';
                        $this->panel['isParent'] = $parent;
                        $this->panel['childEntity'] = $entityName;
                    } else {
                        throw new \Exception('Error. Undefined '.$entityName.' mutable panel on '.$this->moduleName.' module.');
                    }
                } else {
                    throw new \Exception('Error. Invalid entity name.');
                }
            } else {
                throw new \Exception('Error. Invalid entity parameter for withOption method.');
            }
        } else {
            throw new \Exception('Error. withOption method is only applicable for mutable panels.');
        }

        return $this;
    }

    public function hideIn($pages)
    {
        if (array_key_exists('show_in', $this->panel)) {
            throw new \Exception('Error. hide_in and show_in can not be applied on the same panel.');
        }

        if (! is_array($pages)) {
            throw new \Exception('Error. Parameter for hideIn function must be of type array.');
        }
        if (array_diff($pages, ['create', 'show', 'update', 'index'])) {
            throw new \Exception('Error. Invalid parameter for hideIn function.');
        }

        $this->panel['hide_in'] = $pages;

        return $this;
    }

    public function showIn($pages)
    {
        if (array_key_exists('hide_in', $this->panel)) {
            throw new \Exception('Error. hide_in and show_in attribute can not be applied on the same panel.');
        }
        if (! is_array($pages)) {
            throw new \Exception('Error. Parameter for showIn function must be of type array.');
        }
        if (array_diff($pages, ['create', 'show', 'update', 'index', 'quickadd'])) {
            throw new \Exception('Error. Invalid parameter for showIn function.');
        }

        $this->panel['show_in'] = $pages;

        return $this;
    }

    public function hideIf($expresion)
    {
        if (! $expresion) {
            throw new \Exception('Error. Missing parameter in hideIf function.');
        }
        $this->panel['hide_if'] = $expresion;

        return $this;
    }

    public function visibleIf($expresion)
    {
        if (! $expresion) {
            throw new \Exception('Error. Missing parameter in visibleIf function.');
        }

        $this->panel['visible_if'] = $expresion;

        return $this;
    }

    public function onlyWithin($entityName, $values, $key = '_id')
    {
        $availableEntities = ['Country', 'Branch'];

        if (in_array($entityName, $availableEntities)) {
            $entity = Entity::where('name', $entityName);

            if (is_array($values)) {
                $itemId = Entity::whereIn($key, $values)->pluck('_id')->toArray();
            } else {
                $itemId = Entity::where($key, $values)->first()->_id;
            }

            $this->panel['onlyWithin'] = ['entity_id' => $entity->_id, 'values' => $itemId];
        } else {
            throw new \Exception('Invalid entity named "'.$entityName.'" for method onlyWithin. Available entities: '.implode(',', $availableEntities));
        }

        return $this;
    }

    public function generateSectionName()
    {
        $cnt = 0;
        $pre = strtolower($this->entity->name).'-section-';

        do {
            $genName = $pre.++$cnt;
            $check = Section::where('name', $genName)->first();
            if (! $check && $this->sectionList && (count($this->sectionList) > 0)) {
                $key = array_search($genName, array_column($this->sectionList, 'name'));
                if ($key !== false) {
                    $check = true;
                }
            }
        } while ($check);

        return $genName;
    }

    public function addLabel($label)
    {
        if (! $label) {
            throw new \Exception('Error. addLabel function must have a string parameter.');
        }

        $this->panel['label'] = $label;

        return $this;
    }

    public function setName($name)
    {
        if (! $name) {
            throw new \Exception('Error. setName function must have a string parameter.');
        }

        $this->panel['name'] = $name;

        return $this;
    }

    public function save()
    {
        $panel = Panel::create($this->panel);
        foreach ($this->sectionList as $section) {
            $sec = $panel->sections()->create($section);
            $sec->firstColumn()->attach($this->fieldList[$sec->name][0]);
            if (count($this->fieldList[$sec->name]) == 2) {
                $sec->secondColumn()->attach($this->fieldList[$sec->name][1]);
            }
        }

        return $panel;
    }
}
