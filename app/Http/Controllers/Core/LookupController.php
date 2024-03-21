<?php

namespace App\Http\Controllers\Core;

use App\Builders\DynamicQueryBuilder as DQB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Lookup\PaginateResource;
use App\Http\Resources\ModelCollection;
use App\Models\Core\Field;
use App\Models\Module\Module;
use App\Models\User;
use App\Services\FieldService;
use App\Services\LookupService;
use App\Services\PicklistService;
use App\Services\SearchService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class LookupController extends Controller
{
    const DEFAULT_LIMIT = 50;

    use ApiResponseTrait;

    protected $queryBuilder;

    protected User $user;

    protected $request;

    protected static $exemptedModules = ['Employee'];

    public function __construct(DQB $dqBuilder)
    {

        $this->queryBuilder = $dqBuilder;

        $this->user = Auth::guard('api')->user();
        $this->queryBuilder->setUser($this->user);
    }

    public function getLookup(Request $request)
    {

        $this->request = $request;

        return $this->respondFriendly(function () {

            $fieldId = $this->request->input('fieldId');

            if (! $fieldId) {
                throw new \Exception('Error. Unspecified module and/or field for lookup');
            }

            $field = Field::where('_id', $fieldId)->orWhere('uniqueName', $fieldId)->first();
            if (! $field) {
                throw new \Exception('Error. Unknown field Id: '.$fieldId);
            }

            if ($field->fieldType->name != 'lookupModel') {
                throw new \Exception('Field given does not have a source that is of "lookupModel" type', 422);
            }

            $fieldService = new FieldService;
            $moduleName = $this->request->input('moduleName');
            $module = null;
            $entity = $field->relation->entity;
            $isForReport = $this->request->has('forReport');

            // check if user is allowed in the module
            if (! $isForReport && $moduleName && ! $this->user->canView($moduleName)) {
                return [];
            }

            $searchString = $this->request->input('search', null);

            $searchFields = $fieldService->getLookupReturnables($field, true, true, false) ?? [];
            $selectedFields = $fieldService->getLookupReturnables($field) ?? [];

            $isPopup = $isForReport ? true : $fieldService->isPopup($field);
            $limit = $isPopup ? (int) $this->request->input('limit', self::DEFAULT_LIMIT) : null;

            $fields = $field->relation->entity->fields()->whereIn('name', $selectedFields)->get();
            $picklists = (new PicklistService)->getPicklistsFromFields($fields);

            $searchfield = ($entity->name == 'Account') ? $field->relation->entity->fields()->whereIn('name', $searchFields)->get() : $fields;

            $lookupService = new LookupService;

            /************************** Custom lookups ***************************/

            if (! $isForReport) {

                switch ($field->uniqueName) {
                    case 'salesopportunity_pricebook_id':
                        return $lookupService->getOpportunityPricebooks($fields, $picklists);
                        // case 'serviceinclusive_service_id':
                        //     return $lookupService->getInclusiveParticulars($this->request, $limit, $fields, $picklists, $moduleName);
                        // case 'pricelist_product_category_ids':
                        //     return $lookupService->getPricelistProductCategories($this->request, $fields, $picklists);

                }
            }

            /***********************************************************************/

            // check if filterQuery is defined
            $fquery = $field->filterQuery;

            if ($fquery && ! starts_with($fquery, '>>') && ! $isForReport) {

                if (! $moduleName) {
                    throw new \Exception('Error. Module name is required when lookup is from a mutable entity');
                }

                $module = Module::where('name', $moduleName)->first();

                if (! $module) {
                    throw new \Exception('Error. Unknown module named '.$moduleName);
                }

                $filterQuery = $this->queryBuilder->replaceQueryPatterns($fquery, $field->entity);
                $collection = $this->queryBuilder->selectFrom($selectedFields, $entity, true, ($searchString) ? null : $limit)->filterGet($filterQuery);

            } else {

                $entityModel = App::make($field->relation->class);

                if (! is_array($selectedFields)) {
                    $selectedFields = explode(',', $selectedFields);
                }

                if ($isForReport && in_array($field->relation->entity->name, ['Employee', 'User'])) {
                    $collection = $entityModel->whereIn('_id', $this->user->getPeople());
                } elseif ($isForReport && in_array($field->uniqueName, ['salesopportunity_salesperson_in_charge_id', 'salesquote_salesperson_in_charge_id'])) {
                    $accountIds = Account::where('isEscoBranch', true)->pluck('_id')->toArray();
                    $collection = $entityModel->whereIn('account_ids', $accountIds);

                } elseif (! $isForReport && $field->uniqueName == 'salesopportunity_dd_sales_rep_id' && ($this->request->has('distributor_id') || $this->request->has('dealer_id'))) {
                    $accountIds = (array) ($this->request->input('distributor_id') ? $this->request->input('distributor_id') : $this->request->input('dealer_id'));
                    $collection = $entityModel->whereIn('account_ids', $accountIds);
                } else {
                    $collection = $entityModel;
                }
            }

            if ($searchString) {
                $collection = (new SearchService)->checkSearch($collection, $searchfield, $entity->name);
            }

            if ($entity->name == 'Account') {
                $collection->orderBy($searchfield[0]['name'], 'asc');
            }

            if ($field->filterSourceField ?? null && ! $isForReport) {
                $collection = $this->checkFilterSource($collection, $field, $field->filterSourceField);
            }

            if ($field->uniqueName == 'salesopptitem_list_price_id') {
                $coll = $collection->paginate(20);

                return [
                    'values' => new PaginateResource($coll),
                ];
            }

            if ($isPopup && ! ($collection instanceof LengthAwarePaginator)) {
                $collection = $collection->paginate($limit, $selectedFields);
            } elseif (! ($collection instanceof LengthAwarePaginator)) {
                if ($collection instanceof Builder) {
                    $collection = $collection->get($selectedFields);
                } else {
                    $collection = $collection->all($selectedFields);
                }
            }

            return $this->respond([
                'values' => new ModelCollection($collection, $fields, $picklists),
            ]);

        });
    }

    public function getLookupItem()
    {
        return $this->respondFriendly(function () {
            $fieldId = request('fieldId');
            $itemId = request('itemId');

            return $this->setStatusCode(200)->respond((new LookupService)->getAutoFillFields($fieldId, $itemId));
        });
    }

    protected function checkFilterSource($builder, $field, $src)
    {

        $f = $field->rules()->where('name', 'filtered_by')->first()->value ?? null;

        if ($f) {
            $filterSrcValue = request($f, null);
            if ($filterSrcValue) {
                if (is_array($filterSrcValue)) {
                    return $builder->whereIn($src, $filterSrcValue);
                } else {
                    return $builder->where($src, '=', $filterSrcValue);
                }
            }
        }

        return $builder;
    }
}
