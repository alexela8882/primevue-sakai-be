<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PanelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    private $params = [];

    private $ids;

    protected $mutable;

    protected $form;

    public function __construct($params = false, $ids = null, $form = false)
    {
        $this->params = $params;
        $this->ids = $ids;
        $this->form = $form;
    }

    public function toArray(Request $request): array
    {

        if (strpos($this->controllerMethod, '@index') && ! $this->params) {
            $this->mutable = false;
        } elseif ($this->params) {
            $this->mutable = true;
        } else {
            $this->mutable = $this->mutable;
        }

        $return = [
            '_id' => $this->_id,
            'panelName' => $this->name,
            'entityName' => $this->entity->name,
            'controllerMethod' => $this->controllerMethod,
            'label' => $this->label,
            'tabKey' => $this->tabKey,
            'mutable' => $this->mutable,
            'sections' => SectionResource::collection($this->sections),
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

        if ($this->mutable) {
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

        if ($this->form) {
            $this->mutable = true;
        }

        return $return;
    }
}
