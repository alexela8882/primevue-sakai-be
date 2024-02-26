<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Account;
use App\Models\Customer\Lead;
use App\Models\Customer\SalesOpportunity;
use App\Models\Customer\SalesOpptItem;
use App\Models\Customer\SalesQuote;
use App\Models\User;
use App\Services\ModuleDataCollector;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesOpportunityController extends Controller
{
    use ApiResponseTrait;

    public $user;

    public $mdc;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {

        $this->user = Auth::guard('api')->user() ?? User::find('5bf45d4a678f714eac558ba3');
        $this->mdc = $moduleDataCollector->setUser()->setModule('salesopportunities');
    }

    public function index(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            if ($this->user->canView('salesopportunities')) {
                return $this->mdc->data($request);
            }
        });
    }

    public function show($salesopportunity)
    {
        return $this->respondFriendly(function () use ($salesopportunity) {
            return \DataCollector::getConnectedCollectionData($salesopportunity);
        });
    }

    // public function store(Request $request) {

    //     return $this->respondFriendly(function() use ($request){

    //         $validate = $this->validation->validateInput('salesopportunities', $request);

    //         if ($validate)
    //             return $this->respondUnprocessable($validate);

    //         $item = \DataCollector::save($request);

    //         $data = $this->opportunity->getStageHistoryData($request, $item->_id);

    //         if ($data)
    //             $this->oppSH->create($data);

    //         $this->opportunity->find($item->_id)->update(['lastStageUpdateDate' => $item->created_at->format('Y-m-d'),'created_stage_id' => $item->stage_id]);

    //         return $this->respondSuccessful('Sales opportunity successfully saved', $item);
    //     });
    // }

    // public function update($id, Request $request) {
    //     return $this->respondFriendly(function() use ($id, $request){
    //         $validate = $this->validation->validateInput('salesopportunities',$request,'update');
    //         if ($validate)
    //             return $this->respondUnprocessable($validate);

    //         $stageId = $request->get('stage_id');

    //         $opp = $this->opportunity->getModel()->find($id);

    //         $oldStageID = $opp->stage_id;

    //         \DataCollector::update($id, $request);

    //         $data = $this->opportunity->getStageHistoryData($request->all(), $id, null, false, $opp);

    //         if ($data){
    //             $this->oppSH->create($data);
    //         }

    //         if ($stageId != $oldStageID){
    //             $opp->update(['lastStageUpdateDate' => date('Y-m-d')]);
    //         }

    //         if (Input::exists('PONo') && $opp->PONo ?? null !== Input::get('PONo')){
    //             $this->serviceJob->getModel()->where('sales_opportunity_id', $id)->update(['PONo' => Input::get('PONo')]);
    //         }

    //         return $this->respondSuccessful('Sales opportunity successfully updated', $opp->toArray());
    //     });
    // }

    // public function upsert($id, Request $request) {
    //     return $this->respondFriendly(function() use ($id, $request){
    //         $validate = $this->validation->validateInput('salesopportunities', $request, 'upsert');
    //         if ($validate)
    //             return $this->respondUnprocessable($validate);
    //         $item = \DataCollector::upsert($id, $request);

    //         $this->checkQuoteStat($id);

    //         return $this->respond([
    //             'item' => $item, 'message' => 'Items saved'
    //         ]);
    //     });
    // }

    // public function destroy($id){
    //     return $this->respondFriendly(function() use ($id){
    //         $opportunity = $this->opportunity->getModel()->find($id);
    //         if($opportunity){
    //             $opportunity->onLinked('SalesOpptItem')->delete();
    //             $this->quote->getModel()->where('sales_opportunity_id', $id)->delete();
    //             $this->oppSH->getModel()->where('sales_opportunity_id', $id)->delete();
    //             $opportunity->delete();

    //             return $this->respondSuccessful("Sales opportunity successfully deleted.");
    //         }
    //         return $this->respondUnprocessable("Invalid ID.");
    //     });
    // }

    // public function massDelete() {
    //     return $this->respondFriendly(function() {
    //         if(\DataCollector::massDelete())
    //             return $this->respond('Items successfully deleted');
    //     });
    // }

    // public function convert($id, Request $request) {
    //     return $this->respondFriendly(function() use ($id, $request){
    //         $lead = $this->lead->find($id);
    //         if(!$lead)
    //             throw new \Exception('Error. Unknown lead id ' . $id);

    //         try {
    //             $request['lead_id'] = $id;
    //             $request['dateConverted'] = new Carbon('NOW');
    //             $item = \DataCollector::save($request);

    //             $data = $this->opportunity->getStageHistoryData($request, $item->_id);

    //             if ($data)
    //                 $this->oppSH->create($data);

    //             $this->opportunity->find($item->_id)->update(['lastStageUpdateDate' => $item->created_at->format('Y-m-d'), 'created_stage_id' => $item->stage_id]);

    //             $lead->update([
    //                 'converted' => true,
    //                 'status_id' => PickList::getIDs('lead_status', 'Converted to Opportunity')
    //             ]);

    //         } catch(Exception $e) {
    //             $lead->update([
    //                 'converted' => false
    //             ]);

    //             if(env('RESPOND_FRIENDLY', true))
    //                 throw new \Exception('Error in lead conversion');
    //             else
    //                 throw $e;

    //         }

    //         return $this->respondSuccessful('Lead successfuly converted', $item);

    //     });
    // }

    // public function getItemsWithSJ($id){
    //   return $this->respondFriendly(function() use ($id){
    //       $opportunity = $this->opportunity->find($id);
    //       if($opportunity){
    //             if ($opportunity->generatedSJ ?? false){
    //                     $sjs = $this->serviceJob->getModel()->where('sales_opportunity_id', $id)->get();
    //                     return ['data' => $sjs];

    //             }
    //             return ['data' => null];
    //       }
    //       return $this->respondUnprocessable("Invalid ID.");
    //   });
    // }

    // public function getActiveItems($id){
    //   return $this->respondFriendly(function() use ($id){
    //       $opportunity = $this->opportunity->find($id);
    //       if($opportunity){
    //             if (Input::exists('withQuote'))
    //                 $coll = $this->opportunity->getActiveOppItems($id, true);
    //             else
    //                 $coll = $this->opportunity->getActiveOppItems($id, false);

    //             $fields =  $this->opptItem->getEntityFields();
    //             $esPicklists = PickList::getPicklistsFromFields($fields);
    //             return $this->fractalTransformer->createCollection($coll, new ModelTransformer($fields, $esPicklists));

    //       }
    //       return $this->respondUnprocessable("Invalid ID.");
    //   });
    // }

    // public function checkDetails($oppID){

    //       $return = null;
    //       $opportunity = $this->opportunity->getModel()->find($oppID);

    //       if ($opportunity){

    //           $activeOppItem = PickList::getIDs('oppt_item_status', 'Active');

    //           $checkquote = $this->quote->getModel()->where('sales_opportunity_id', $opportunity->_id)->get();
    //           $return['hasQuote'] = $checkquote->count() ? true : false;

    //           if ($checkquote->count()) {
    //               $item = $opportunity->onLinked('SalesOpptItem')->where('sales_quote_id', '!=', null)->where('status', $activeOppItem)->first();

    //               if ($item)
    //                   $return['hasActiveQuote'] = true;
    //               else
    //                   $return['hasActiveQuote'] = false;
    //           }
    //           else
    //               $return['hasActiveQuote'] = false;

    //           return $return;

    //       }

    //       return ['message' => 'Invalid opportunity ID'];

    // }

    // public function checkQuoteStat($id){

    //     $voidOppItem = PickList::getIDs('oppt_item_status', 'Void');
    //     $activeOppItem = PickList::getIDs('oppt_item_status', 'Active');
    //     $voidQuote = PickList::getIDs('quote_status', 'Void');
    //     $activeQuote = PickList::getIDs('quote_status', 'Active');

    //     $qts = $this->quote->getModel()->where('sales_opportunity_id',$id)->get();

    //     foreach ($qts as $qt) {
    //         $q = $this->opptItem->getModel()->where('sales_quote_id', $qt->_id)->where('status_id', $activeOppItem)->get();
    //         if (!$q->count() && $qt->status_id != $voidQuote)
    //             $qt->update(['status_id' => $voidQuote]);
    //         elseif ($q->count() && $qt->status_id == $voidQuote)
    //             $qt->update(['status_id' => $activeQuote]);

    //         $this->compute($qt, "SalesQuote");
    //     }

    //     $qt = $this->opportunity->getModel()->find($id);

    //     $this->compute($qt, "SalesOpportunity");

    // }

    // public function compute($model, $entity){
    //     (new Compute)->handle($model, $entity);

    //     //   $entity = $this->entityRepository->getModel()->where('name', $entity)->first();
    //     //   RusResolver::setEntity($entity);
    //     //   FormulaParser::setEntity($entity);

    //     //   $fields = $entity->fields()->get();

    //     //   list($formula, $rus) = $this->getRusAndFormula($fields);

    //     //   if(count($formula)){
    //     //       usort($formula, function($a, $b) {
    //     //         return $a['hierarchy'] <=> $b['hierarchy'];
    //     //       });
    //     //   }

    //     //   $update = [];
    //     //   if(count($rus)){
    //     //       foreach ($rus as $rusField) {
    //     //           $v =  $model->{$rusField->name} ?? null;
    //     //           $value = RusResolver::resolve($model, $rusField);
    //     //           $update[$rusField->name] = $value;
    //     //       }
    //     //       $model->update($update);
    //     //   }

    //     //   $model->save();

    //     //   if(count($formula)){
    //     //       foreach ($formula as $formulaField) {
    //     //           $value = FormulaParser::parseField($formulaField, $model, true);
    //     //           $model->update([$formulaField->name => $value]);
    //     //       }
    //     //   }
    // }

    // public function getAccountIds($id){
    //   $qts = $this->quote->getModel()->where('sales_opportunity_id',$id)->get();
    //   $accounts = [];
    //   foreach ($qts as $key => $qt) {
    //     if($qt->ship_to_name_id){
    //       $acc = $this->account->find($qt->ship_to_name_id)->getModel();
    //       $accounts[] = ['_id'=>$acc->_id,'name'=>$acc->name,'isEscoBranch'=>$acc->isEscoBranch,'quote_id'=>$qt->_id];
    //     }

    //   }
    //   return $accounts;
    // }

    // public function getRusAndFormula($fields, $query = null){

    //     $formulaFields = [];
    //     $rusField = [];

    //     foreach ($fields as $field) {
    //         if($field->fieldType->name == 'formula') {
    //             $formulaFields[] = $field;
    //             continue;
    //         }
    //         if($field->fieldType->name == 'rollUpSummary') {
    //             if ($query){
    //                     if ($field->rusEntity == $query)
    //                         $rusField[] = $field;
    //             }
    //             else {
    //               $rusField[] = $field;
    //             }
    //             continue;
    //         }
    //     }

    //     return[$formulaFields,$rusField];

    // }

    // public function transferOpportunity($opptID)
    // {

    //     return $this->respondFriendly(function() use ($opptID){
    //         $newAccountID = Input::get('account_id');
    //         $so = $this->opportunity->getModel()->find($opptID);

    //         if(!$so)
    //             return $this->respondUnprocessable("Invalid Opportunity ID.");

    //         $acc = $this->account->getModel()->find($newAccountID);
    //         if(!$acc)
    //             return $this->respondUnprocessable("Invalid Account ID.");

    //         $odata = [
    //             'account_id' => $newAccountID,
    //             'old_account_id' => $so->account_id
    //         ];

    //         $so->update($odata);

    //         $this->quote->getModel()->where('sales_opportunity_id', $opptID)
    //              ->update([
    //                 'quoteToEmail' => null,
    //                 'quoteToPhoneNo' => null,
    //                 'quote_to_name_id' => null,
    //                 'quoteToName' => null,
    //                 'account_id' => $newAccountID,
    //                 'old_account_id' => $so->account_id,

    //                 'billingStreet' => null,
    //                 'billingZipCode' => null,
    //                 'billingCity' => null,
    //                 'billingState' => null,
    //                 'billing_country_id' => null,

    //                 'billToName' => null,
    //                 'billEmail' => null,
    //                 'billPhoneNo' => null,
    //                 'bill_to_name_id' => null,

    //                 'shippingStreet' => null,
    //                 'shippingZipCode' => null,
    //                 'shippingCity' => null,
    //                 'shippingState' => null,
    //                 'shipping_country_id' => null,

    //                 'shipToName' => null,
    //                 'shipEmail' => null,
    //                 'shipPhoneNo' => null,
    //                 'ship_to_name_id' => null,

    //              ]);

    //         return $this->respondSuccessful('Opportunity and Quotes successfuly transfered',[]);

    //     });

    // }

    // public function getWonItems($accountid){

    //     return $this->respondFriendly(function() use ($accountid){

    //         $limit = (int) Input::get('limit', 5);
    //         $page = (int) Input::get('page', 1);
    //         $skip = $limit * ($page-1);
    //         $sortField =  'created_at';

    //         $wonId = PickList::getIDs('opportunity_status', 'Closed Won');
    //         $stat = PickList::getIDs('oppt_item_status', 'Active');
    //         $equi = PickList::getIDs('product_types', 'Equipment');

    //         $aggregate[]['$match'] = ['$and' => [['account_id' => $accountid], ['stage_id' => $wonId]]];

    //         $aggregate[]['$lookup'] = [
    //             'from' =>  'sales_oppt_items',
    //             'localField' => 'oid',  // field in related collection
    //             'foreignField' => 'sales_opportunity_id',  // field in 'from' collection
    //             'as' => 'items'
    //         ];

    //         $aggregate[]['$match'] =  ['items.status_id' => $stat];
    //         $aggregate[]['$unwind'] = '$items';
    //         $aggregate[]['$match'] = ['items' => ['$ne' => []]];

    //         $aggregate[] = ['$addFields' => ['product_oid' => '$items.product_id']];

    //         $aggregate[]['$lookup'] = [
    //             'from' =>  'products',
    //             'localField' => 'product_oid',  // field in related collection
    //             'foreignField' => 'oid',  // field in 'from' collection
    //             'as' => 'product'
    //         ];

    //         $aggregate[]['$match'] =  ['product.product_type_id' => $equi];
    //         $aggregate[]['$match'] = ['items' => ['$ne' => []]];

    //         $xT = $this->opportunity->getModel()->raw(function($rec) use ($aggregate){
    //             return $rec->aggregate( $aggregate , ['allowDiskUse' => true] );
    //          })->count();

    //         $aggregate[]['$sort'] = [$sortField => -1];
    //         $aggregate[]['$skip'] = $skip;
    //         $aggregate[]['$limit'] = $limit;

    //         $x = $this->opportunity->getModel()->raw(function ($l) use ($aggregate) {
    //             return $l->aggregate($aggregate, ['allowDiskUse' => true]);
    //         });

    //         $totalpage = ($xT > $limit) ? ceil($xT / $limit) : $totalpage = 1;

    //         $return['data'] =  $this->fractalTransformer->createCollection($x, new OpportunityItemTransformer());
    //         $return['meta']['pagination'] = ['count' => $x->count(), 'current_page' => $page, 'per_page' => $limit, 'total' => $xT, 'total_pages' => $totalpage];
    //         return $return;

    //    //     return DataCollector::setTransformer(new OpportunityItemTransformer())->checkSearchPaginate($list,null,null);

    //      //   return $this->fractalTransformer->createCollection($list, );

    //     });
    // }

    // public function editWebView()
    // {
    //     return view('index');
    // }

}
