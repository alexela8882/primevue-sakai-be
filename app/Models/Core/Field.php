<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Field extends Base
{
    protected $connection = 'mongodb';

    protected $guarded = ['id'];

    public function relation()
    {
        return $this->hasOne(Relation::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function fieldType()
    {
        return $this->belongsTo(FieldType::class);
    }

    public function rules()
    {
        return $this->embedsMany(Rule::class);
    }

    public function hasMultipleValues()
    {
        if (! $this->fieldType) {
            return false;
        }

        $type = $this->fieldType->name;

        return $type == 'lookupModel' && $this->relation->method == 'belongsToMany' ||
            $type == 'picklist' && $this->rules()->whereIn('name', ['ms_dropdown', 'ms_list_view', 'checkbox_inline', 'checkbox', 'tab_multi_select', 'ms_pop_up', 'checkbox_inline'])->count();
    }

    public function isPopup()
    {
        return $this->rules()->whereIn('name', ['ss_pop_up', 'ms_pop_up'])->count() ? true : false;
    }
}
