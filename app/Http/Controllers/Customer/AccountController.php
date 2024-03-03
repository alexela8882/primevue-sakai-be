<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Core\Field;
use App\Models\Customer\Account;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser()->setModule('accounts');
    }

    public function index(Request $request)
    {
        return $this->moduleDataCollector->getIndex($request);
    }

    public function store(Request $request)
    {
        $lead = $this->moduleDataCollector->postStore($request);
    }

    public function show(Account $account, Request $request)
    {
        return $this->moduleDataCollector->getShow($account, $request);
    }

    public function postMergeDuplicateAccounts(string $identifier, Request $request)
    {
        $accountIds = (array) $request->input('account_to_merge');

        $accountEntity = $this->moduleDataCollector->entity;

        $relations = $accountEntity->load(['relations', 'relations.field', 'relations.field.entity'])->relations;

        foreach ($relations as $relation) {
            $field = $relation->field;

            if ($field instanceof Field) {
                if ($relation->method === 'belongsToMany') {
                    $field->entity
                        ->getModel()
                        ->whereIn($field->name, $accountIds)->each(function ($model, $relation, $identifier, $accountIds) {
                            $query = $model->dynamicRelationship('belongsToMany', $relation->class, $relation->foreign_key, $relation->local_key, null, true);

                            $query->detach($accountIds);

                            $query->attach($identifier);
                        });
                } else {
                    $field->entity->getModel()->whereIn($field->name, $accountIds)->update([$field->name => $identifier]);
                }
            }
        }

        Account::whereIn('_id', $accountIds)->delete();

        $this->moduleDataCollector->patchUpdate($identifier, $request);
    }
}
