<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PanelResource extends JsonResource
{
    // FormController , QuickAddController
    private static bool $isParams;

    private static mixed $identifiers;

    private static bool $isForm;

    private static bool $isMutable;

    public static function customCollection($resource, bool $isParams = false, mixed $identifiers = null, bool $isForm = false)
    {
        self::$isParams = $isParams;

        self::$identifiers = $identifiers;

        self::$isForm = $isForm;

        return parent::collection($resource);
    }

    public function toArray(Request $request): array
    {
        if (strpos($this->controllerMethod, '@index') && ! self::$isParams) {
            self::$isMutable = false;
        } elseif (self::$isParams) {
            self::$isMutable = true;
        } else {
            self::$isMutable = $this->mutable;
        }

        $return = [
            '_id' => $this->_id,
            'panelName' => $this->name,
            'entityName' => $this->entity->name,
            'controllerMethod' => $this->controllerMethod,
            'label' => $this->label,
            'tabKey' => $this->tabKey,
            'mutable' => $this->mutable,
            'sections' => SectionResource::customCollection($this->sections, self::$isMutable, self::$identifiers),
            'highlight' => $this->highlight,
        ];

        if ($this->show_in) {
            $return['rules']['show_in'] = $this->show_in;
        }

        if ($this->hide_in) {
            $return['rules']['hide_in'] = $this->hide_in;
        }

        if ($this->hide_if) {
            $return['rules']['hide_if'] = $this->hide_if;
        }

        if ($this->visible_if) {
            $return['rules']['visible_if'] = $this->visible_if;
        }

        if ($this->onlyWithin) {
            $return['rules']['onlyWithin'] = $this->onlyWithin;
        }

        if (! $this->highlight) {
            $return['highlight'] = null;
        }

        if (self::$isMutable) {
            $return['selectionField'] = $this->selectionField;
            $return['mutableType'] = $this->mutableType;
            $return['tabName'] = $this->tabName;
            $return['required'] = $this->required ?? false;
            $return['isChild'] = $this->isChild ?? false;

            if ($this->panelview) {
                $return['panelview'] = $this->panelview;
            }

            if ($this->mutableType == 'withOption') {
                $return['isParent'] = $this->isParent;
                $return['childEntity'] = $this->childEntity;
            }
            if ($this->compute) {
                $return['compute'] = $this->compute;
            }
        }

        if (self::$isForm) {
            self::$isMutable = true;
        }

        return $return;
    }
}
