<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\PanelResource;
use App\Models\Core\Entity;
use App\Services\ModuleDataCollector;
use App\Services\PanelService;
use App\Traits\ApiResponseTrait;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;

class QuickAddController extends Controller
{
    use ApiResponseTrait;

    protected $validation;

    protected static $qEntities = ['AddressBook', 'Lead', 'EscoVenturesAccount', 'Campaign', 'Account', 'ServiceParticular', 'ServiceOpportunity', 'SalesOpportunity', 'Contact', 'DrugPipeline'];

    public $fractalTransformer;

    public $entityRepository;

    public $panelRepo;

    public $user;

    public $address;

    public function __construct(private ModuleDataCollector $mdc)
    {

    }

    public function getAllPanelByEntity($entityName)
    {
        return $this->respondFriendly(function () use ($entityName) {

            $entity = Entity::where('name', $entityName)->orWhere('_id', $entityName)->first();
            if (! $entity) {
                return $this->respondUnprocessable('Invalid entity.');
            }

            if (in_array($entity->name, static::$qEntities)) {

                if ($entity->name == 'ServiceParticular') {
                    $allPanels = (new PanelService)->getAllPanel('services', 'mutableOnly', $entity->_id);
                } else {
                    $module = str_plural(strtolower($entity->name));
                    $allPanels = (new PanelService)->getAllPanel($module, 'mainOnly');
                }
                if (Input::exists('all')) {
                    $fields = $entity->fields->pluck('_id')->toArray();
                } else {
                    $fields = $entity->fields->where('quick', true)->pluck('_id')->toArray();
                }

                $panels = PanelResource::customCollection($allPanels, true, $fields);

                foreach ($panels as $panel => $pan) {
                    foreach ($pan['sections'] as $key => $sec) {
                        if (count(array_flatten($sec['field_ids'] ?? [])) == 0) {
                            unset($panels[$panel]['sections'][$key]);
                        }
                    }
                }

                return $panels;

            } else {
                return $this->respondUnprocessable('Entity does not support quick add.');
            }
        });
    }

    public function store($id, Request $request)
    {
        return $this->respondFriendly(function () use ($id, $request) {

            $this->mdc->setUser();

            $entity = Entity::where('name', $id)->orWhere('_id', $id)->first();

            if (in_array($entity->name, static::$qEntities)) {

                if ($entity->name == 'ServiceParticular') {
                    $this->mdc->setModule('services');
                    $base = $entity->getModel()->find($id);

                    // $validate = $this->validation->validateInput('services', $request, 'upsert', true);

                    // if ($validate)
                    //     return $this->respondUnprocessable($validate);

                    $item = $this->mdc->patchUpsert($base, $request);
                } else {
                    $module = str_plural(strtolower($entity->name));
                    $this->mdc->setModule($module);

                    if ($entity->name == 'Contact') {
                        $check = $entity->getModel()->where('account_ids', (array) $request->account_ids)->where('email', $request->email)->where('firstName', $request->firstName)->where('lastName', $request->lastName)->count();
                        if ($check) {
                            return $this->respondUnprocessable('Error. Email address already exists in the selected account.');
                        }
                    }
                    $item = $this->mdc->postQuickAdd($request, true);

                    if ($entity->name == 'Account') {
                        if ($request->owner_id ?? null) {
                            $user = User::find($request->owner_id);
                            if ($user) {
                                $item->update(['branch_id' => $user->branch_id ?? null]);
                            }
                        }
                        $this->address->saveAddress($item);
                    }
                }

                if ($entity->name == 'AddressBook' && Input::get('servicePrimary', null) == true) {
                    $this->address->getModel()->where('account_id', $item->account_id)->where('_id', '!=', $item->_id)->where('servicePrimary', true)->update(['servicePrimary' => false]);
                }

                return $this->respondSuccessful($entity->name.' successfully saved', $item);

            }

        });
    }
}
