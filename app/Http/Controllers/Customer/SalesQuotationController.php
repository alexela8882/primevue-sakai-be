<?php

namespace App\Http\Controllers\Customer;

use App\Facades\FormulaParser;
use App\Facades\PickList;
use App\Facades\RoleAccess;
use App\Facades\RusResolver;
use App\Http\Controllers\Controller;
use App\Repositories\Eloquent\Core\EntityRepository;
use App\Repositories\Eloquent\Customer\SalesOpportunityRepository;
use App\Repositories\Eloquent\Customer\SalesOpptItemRepository;
use App\Repositories\Eloquent\Customer\SalesQuoteRepository;
use App\Services\SalesOpportunity\Compute;
use App\Services\Validation;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class SalesQuotationController extends Controller
{
    use ApiResponseTrait;

    private $qt;

    private $fields;

    private $user;

    private $validation;

    private $oppItem;

    private $salesOppt;

    private $entityRepository;

    public function __construct(SalesOpportunityRepository $salesOppt, EntityRepository $entityRepository, SalesQuoteRepository $qt, Validation $validation, SalesOpptItemRepository $oppItem)
    {
        $this->qt = $qt;
        $this->fields = $this->qt->getEntityFields();
        $this->validation = $validation;
        $this->oppItem = $oppItem;
        $this->entityRepository = $entityRepository;
        $this->salesOppt = $salesOppt;

        \Auth::shouldUse('api');
        $this->user = \Auth::guard('api')->user();

        \DataCollector::setUser($this->user)->setModule('salesquotes');
    }

    public function index()
    {
        if ($this->user) {
            return $this->respondFriendly(function () {
                RoleAccess::check($this->user->_id, 'salesquotes.index', $this->qt);

                return \DataCollector::getMainCollectionData();
            });
        }

        return redirect('/');

    }

    public function show($id)
    {
        return $this->respondFriendly(function () use ($id) {
            $data = $this->qt->getModel()->find($id);

            if ($data) {
                saveToRecent([
                    'user_id' => $this->user->_id,
                    'entity' => 'SalesQuote',
                    'object_id' => $id,
                ]);

                activities()->by($this->user)->on($data)->log('viewed');
            }

            return \DataCollector::getConnectedCollectionData($id);
        });
    }

    public function store(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            $validate = $this->validation->validateInput('salesquotes', $request);
            if ($validate) {
                return $this->respondUnprocessable($validate);
            }
            $item = \DataCollector::save($request);

            saveToRecent([
                'user_id' => $this->user->_id,
                'entity' => 'SalesQuote',
                'object_id' => $item->_id,
            ]);

            activities()->by($this->user)->on($item)->log('created');

            return $this->respond([
                'item' => $item, 'message' => 'Quotation successfully created.',
            ]);

        });
    }

    public function update($id, Request $request)
    {
        return $this->respondFriendly(function () use ($id, $request) {

            $qt = $this->qt->find($id);

            if ($qt) {
                $validate = $this->validation->validateInput('salesquotes', $request, 'update');
                if ($validate) {
                    return $this->respondUnprocessable($validate);
                }

                $item = \DataCollector::update($id, $request);

                activities()->by($this->user)->on($qt)->log('updated');

                $this->checkQuoteStat($id, true);

                return $this->respondSuccessful('Quotation successfully updated', $item);
            }

            return $this->respondUnprocessable('Invalid ID');

        });
    }

    public function upsert($id, Request $request)
    {
        return $this->respondFriendly(function () use ($id, $request) {
            $validate = $this->validation->validateInput('salesquotes', $request, 'upsert');

            if ($validate) {
                return $this->respondUnprocessable($validate);
            }

            $item = \DataCollector::upsert($id, $request);
            $this->checkQuoteStat($id);

            return $this->respond([
                'item' => $item, 'message' => 'Items saved',
            ]);
        });
    }

    public function destroy($id) //edit opptitem's quote_id
    {return $this->respondFriendly(function () use ($id) {
        $qt = $this->qt->find($id);
        if ($qt) {
            $this->qt->delete($id);
            deleteRecent($id);

            return $this->respondSuccessful('Sales quotation successfully deleted.');
        }

        return $this->respondUnprocessable('Invalid ID.');
    });
    }

    public function checkQuoteStat($id, $main = false)
    {
        $qtM = $this->qt->find($id);
        $voidOppItem = PickList::getIDs('oppt_item_status', 'Void');
        $activeOppItem = PickList::getIDs('oppt_item_status', 'Active');
        $voidQuote = PickList::getIDs('quote_status', 'Void');
        $activeQuote = PickList::getIDs('quote_status', 'Active');

        if ($qtM) {
            if ($main) {
                if ($qtM->status_id == $voidQuote) {
                    $this->oppItem->getModel()->where('sales_quote_id', $id)->where('status_id', $activeOppItem)->update(['status_id' => $voidOppItem]);
                }
            }
        }

        $qts = $this->qt->getModel()->where('sales_opportunity_id', $qtM->sales_opportunity_id)->get();

        foreach ($qts as $qt) {
            $q = $this->oppItem->getModel()->where('sales_quote_id', $qt->_id)->where('status_id', $activeOppItem)->get();
            if (! $q->count() && $qt->status_id != $voidQuote) {
                $qt->update(['status_id' => $voidQuote]);
            } elseif ($q->count() && $qt->status_id == $voidQuote) {
                $qt->update(['status_id' => $activeQuote]);
            }

            $this->compute($qt, 'SalesQuote');
        }

        $oppt = $this->salesOppt->find($qtM->sales_opportunity_id);
        $this->compute($oppt, 'SalesOpportunity');
    }

    public function getActiveItems($id)
    {
        return $this->respondFriendly(function () use ($id) {
            $opportunity = $this->opportunity->find($id);
            if ($opportunity) {
                if (Input::exists('withQuote')) {
                    $coll = $this->opportunity->getActiveOppItems($id, true);
                } else {
                    $coll = $this->opportunity->getActiveOppItems($id, false);
                }

                $fields = $this->oppItem->getEntityFields();
                $esPicklists = PickList::getPicklistsFromFields($fields);

                return $this->fractalTransformer->createCollection($coll, new ModelTransformer($fields, $esPicklists));

            }

            return $this->respondUnprocessable('Invalid ID.');
        });
    }

    public function compute($model, $entity)
    {
        (new Compute)->handle($model, $entity);

        //   $entity = $this->entityRepository->getModel()->where('name', $entity)->first();
        //   RusResolver::setEntity($entity);
        //   FormulaParser::setEntity($entity);

        //   $fields = $entity->fields()->get();

        //   list($formula, $rus) = $this->getRusAndFormula($fields);

        //   if(count($formula)){
        //       usort($formula, function($a, $b) {
        //         return $a['hierarchy'] <=> $b['hierarchy'];
        //       });
        //   }

        //   $update = [];
        //   if(count($rus)){
        //       foreach ($rus as $rusField) {
        //           $v =  $model->{$rusField->name} ?? null;
        //           $value = RusResolver::resolve($model, $rusField);
        //           $update[$rusField->name] = $value;
        //       }
        //       $model->update($update);
        //   }

        //   $model->save();

        //   if(count($formula)){
        //       foreach ($formula as $formulaField) {
        //           $value = FormulaParser::parseField($formulaField, $model, true);
        //           $model->update([$formulaField->name => $value]);
        //       }
        //  }
    }

    public function getRusAndFormula($fields, $query = null)
    {

        $formulaFields = [];
        $rusField = [];

        foreach ($fields as $field) {
            if ($field->fieldType->name == 'formula') {
                $formulaFields[] = $field;

                continue;
            }
            if ($field->fieldType->name == 'rollUpSummary') {
                if ($query) {
                    if ($field->rusEntity == $query) {
                        $rusField[] = $field;
                    }
                } else {
                    $rusField[] = $field;
                }

                continue;
            }
        }

        return [$formulaFields, $rusField];

    }
}
