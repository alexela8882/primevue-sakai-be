<?php

namespace App\Services;

use App\Models\Core\Module;
use Illuminate\Support\Facades\Validator;

class ValidationService
{
    private $backEndRules;

    private $mainEntity;

    protected $requiredRules = ['required', 'required_if', 'required_with', 'required_without', 'required_with_all', 'required_without_all', 'required_unless'];

    public function __construct(private PanelService $panelService)
    {
        $this->backEndRules = $this->backEndRules();

    }

    public function onCreate($moduleName)
    {
        $this->validateInput($moduleName);
    }

    public function onUpdate($moduleName)
    {
        $this->validateInput($moduleName, 'mainOnly', 'update');
    }

    public function onUpsert($moduleName)
    {
        $this->validateInput($moduleName, 'mutablesOnly', 'upsert');
    }

    public function onQuickAdd($moduleName)
    {
        $this->validateInput($moduleName, 'mainOnly', 'create', true);
    }

    private function validateInput($moduleName, $panelType = null, $page = 'create', $quickAdd = false)
    {

        $panels = $this->panelService->getAllPanel($moduleName, $panelType);

        $this->mainEntity = Module::getMain($moduleName);

        if (! $panels) {
            return null;
        }

        $rules = [[], []];
        $required = [];

        foreach ($panels as $panel) {
            if ((! $panel->hide_in && ! $panel->show_in) || ($panel->hide_in && ! (in_array($page, $panel->hide_in))) || $panel->show_in && in_array($page, $panel->show_in)) {

                $hidden = request('hiddenFields', []);
                $fields = $this->panelService->getPanelField($panel->name);

                $fields = array_diff($fields, $hidden);

                $fields = Field::whereIn('_id', $fields)->get();

                $entityName = $panel->entity->name;

                if ($quickAdd) {
                    $fields = $fields->where('quick', true);
                }

                if ($panel->required && ! $page == 'upsert') {
                    $required = array_merge($required, [$entityName]);
                }

                $ext = $this->getTypeAndExtract($quickAdd, $fields, $entityName, $page !== 'create' ? request('_id', '_id') : null);

                $rules[0] = array_merge($rules[0], $ext[0]);
                $rules[1] = array_merge($rules[1], $ext[1]);
            }
        }

        if ($required) {
            $request = request('mutables', []);
            foreach ($required as $requiredMutable) {
                if (! $request['mutable_'.$requiredMutable]) {
                    return response('Error.'.$requiredMutable.' panel is required. Please specify at least one item.', 422);
                }
            }
        }

        $firstValidator = Validator::make($request->all(), $rules[0]);
        $secondValidator = Validator::make($request->all(), $rules[1]);
        if ($firstValidator->fails() || $secondValidator->fails()) {
            $errors = $firstValidator->messages()->merge($secondValidator->messages());

            return response($errors, 422);
        }

        return null;
    }

    public function getTypeAndExtract($quickAdd, $fields, $entityName, $id = null)
    {

        $requiredValidation = [];
        $validation = [];
        $ignore = null;
        $ignore2 = null;
        if ($entityName == $this->mainEntity->name) {
            $pre = null;
        } else {
            $pre = 'mutable_'.$entityName.'.*.';
        }

        if ($id) {
            $ignore = ','.$pre.$id.',_id';
            $ignore2 = ','.$pre.$id.'= _id';

            if ($entityName == 'Employee') {
                $collectionName = 'users';
            } else {
                $collectionName = str_plural(snake_case($entityName));
            }

            if ($entityName == $this->mainEntity->name) {
                $validation[] = ['_id' => 'exists:'.$collectionName];
            } else {
                $validation[] = [$pre.'_id' => 'test_exists:'.$collectionName];
            }
        }

        if ($fields) {

            foreach ($fields as $field) {

                if (array_key_exists('rules', $field)) {

                    $fieldRules = $field->rules->pluck('name')->toArray();

                    if (! in_array('read_only', $fieldRules) && ! in_array('label', $fieldRules)) {

                        $stype = $field->fieldType->name;
                        $extractedRule = null;
                        $extractedRequiredRule = null;

                        if (in_array($stype, ['boolean', 'file', 'image'])) {
                            $extractedRule = $stype;
                        } elseif (in_array($stype, ['number', 'currency'])) {
                            $extractedRule = 'numeric';
                        } elseif ($stype == 'time') {
                            $extractedRule = 'date_format:H:i:s';
                        }

                        foreach ($field->rules as $rule) {

                            $stringRule = null;
                            $requiredRule = null;

                            if (in_array($rule->name, $this->backEndRules['noValue'])) {
                                $stringRule = $rule->name;
                            } elseif ($rule->name == 'date_time') {
                                $extractedRule = 'date_format:Y-m-d H:i:s';
                            } elseif ($rule->name == 'date_only') {
                                $extractedRule = 'date_format:Y-m-d';
                            } elseif (in_array($rule->name, $this->backEndRules['withValue'])) {
                                if (is_array($rule->value)) {
                                    $rule->value = implode(',', $rule->value);
                                }

                                $stringRule = $rule->name.':'.$rule->value;
                            } elseif (in_array($rule->name, $this->backEndRules['hasObject'])) {
                                $stringRule = $rule->name.':'.collect($rule->value)->flatten()->implode(',');
                            } elseif (in_array($rule->name, $this->backEndRules['hasObject1'])) {
                                if ($rule->name == 'in_range_with') {
                                    $stringRule = $rule->name.':'.$pre.collect($rule->value)->flatten()->implode(',');
                                } else {
                                    $requiredRule = $rule->name.':'.$pre.collect($rule->value)->flatten()->implode(',');
                                }
                            } elseif ($rule->name == 'unique_with') {
                                if ($entityName == $this->mainEntity->name) {
                                    $stringRule = $rule->name.':'.collect($rule->value)->flatten()->implode(',').$ignore2;
                                } else {
                                    $stringRule = 'test_unique_with:'.collect($rule->value)->flatten()->implode(','.$pre).','.$pre.'_id';
                                }
                            } elseif (in_array($rule->name, $this->backEndRules['withFValue'])) {

                                if (is_array($rule->value)) {
                                    $rule->value = implode(','.$pre, $rule->value);
                                }

                                $requiredRule = $rule->name.':'.$pre.$rule->value;
                            } elseif ($rule->name == 'unique') {
                                if ($entityName == 'Employee') {
                                    $stringRule = $rule->name.':users,'.$field['name'].$ignore;
                                } elseif ($entityName == $this->mainEntity->name) {
                                    $stringRule = $rule->name.':'.$rule->value.','.$field['name'].$ignore;
                                } else {
                                    $stringRule = 'test_unique:'.$rule->value.$pre.$field['name'].$ignore;
                                }
                            } elseif ($stype == 'picklist' && $rule->name == 'filtered_by') {
                                $stringRule = 'in_category:'.$pre.$rule->value.','.$field['listName'];
                            } elseif ($stype == 'picklist') {
                                $stringRule = 'in_picklist:'.$field['listName'];
                            }

                            if ($extractedRule && $stringRule) {
                                $extractedRule = $extractedRule.'|'.$stringRule;
                            } elseif ($stringRule) {
                                $extractedRule = $stringRule;
                            }

                            if ($extractedRequiredRule && $requiredRule) {
                                $extractedRequiredRule = $extractedRequiredRule.'|'.$requiredRule;
                            } elseif ($requiredRule) {
                                $extractedRequiredRule = $requiredRule;
                            }
                        }

                        if ($extractedRule) {
                            if (! in_array('required', $fieldRules)) {
                                $extractedRule = $extractedRule.'|nullable';
                            }

                            $validation[$pre.$field['name']] = $extractedRule;
                        }

                        if ($extractedRequiredRule) {
                            $requiredValidation[$pre.$field['name']] = $extractedRequiredRule;
                        }

                    }
                }

            }
        }

        return [$validation, $requiredValidation];
    }

    public function validateQuotes($moduleName, $page = 'create')
    {
        $this->validateInput($moduleName, request('quote'), $page);
        $this->validateInput('salesopportunities', request('opportunity'), 'upsert');

        return $this;
    }

    public function backEndRules()
    {
        $rules = [
            'noValue' => ['required', 'email', 'url', 'string', 'fax', 'phone', 'alpha', 'alpha_num', 'alpha_dash', 'same'],
            'hasObject' => ['between', 'digits_between', 'select_between'],
            'hasObject1' => ['in_range_with'],
            'required1' => ['required_if', 'required_unless'],
            'withValue' => ['max', 'mimes'],
            'withFValue' => ['required_with', 'required_without', 'required_with_all', 'required_without_all'],
        ];

        return $rules;
    }
}
