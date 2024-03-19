<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Company\BusinessUnit;
use App\Models\Company\Campaign;
use App\Models\Core\Country;
use App\Models\Core\Entity;
use App\Models\Core\Picklist;
use App\Models\Customer\Lead;
use App\Services\LeadAssignmentService;
use App\Services\ModuleDataCollector;
use App\Services\PicklistService;
use App\Traits\GlobalTrait;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    use GlobalTrait;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser()->setModule('leads');
    }

    public function index(Request $request)
    {
        return $this->moduleDataCollector->getIndex($request);
    }

    public function store(Request $request)
    {
        $lead = $this->moduleDataCollector->postStore($request);

        if (! $request->exists('owner_id')) {
            $leadAssignmentService = new LeadAssignmentService;
            [$owner, $bdmLists] = $leadAssignmentService->evaluation($lead);
            //list($source, $productsOfInterest, $inquiryType) = $this->sendOwnerEmail($owner, $lead, 'leads', $bdmLists->pluck('email')->toArray());

            if ($request->exists('from')) {
                if ($request->from == 'rfq') {
                    //$this->sendCustomerEmail($lead, 'leads');
                    $lead->update(['created_by' => '5bb104ed678f71061f645215', 'updated_by' => '5bb104ed678f71061f645215']);
                }
            }
        }

        return $lead?->_id;
    }

    public function show(Lead $lead, Request $request)
    {
        return $this->moduleDataCollector->getShow($lead, $request);
    }

    public function update(Lead $lead, Request $request)
    {
        return $this->moduleDataCollector->patchUpdate($lead, $request);
    }

    public function storeLifesciencesRFQ(Request $request)
    {

        logDrf($request->all(), 'lead-escolifesciences');

        $verifyResponse = $this->verifyCaptcha($request);

        if ($verifyResponse === 0) {
            return ['message' => 0];
        } elseif ($verifyResponse === 1) {
            $data = $this->stripAllTags($request);

            $campaign = Campaign::where('rfqCode', $data['campaign_code'])->first();

            if ($campaign) {

                $country = Country::where('alpha2Code', 'like', '%'.$data['country_iso_code'].'%')->first();

                $lists = PickList::whereIn('name', ['lead_source', 'lead_type', 'lead_status', 'lead_inquiry_types', 'lead_learn_products'])->get()->map(function ($list) {
                    return [$list->name => $list->listItems->pluck('_id', 'value')->toArray()];
                })->collapse()->toArray();

                if ($data['form'] == 'request-for-quotation') {

                    $listItems = PickList::where('name', 'lead_rfq_poi')->first()->listItems->whereIn('uniqueText', $data['products']);

                    $businessUnits = BusinessUnit::whereIn('name', $listItems->pluck('businessUnit')->toArray())->get()->pluck('_id')->toArray();
                }

                $request = new Request([
                    /* Request Data */
                    'firstName' => $data['firstName'] ? $data['firstName'] : null,
                    'lastName' => $data['lastName'] ? $data['lastName'] : null,
                    'email' => $data['email'] ? $data['email'] : null,
                    'street' => $data['streetAddress'] ? $data['streetAddress'] : null,
                    'city' => $data['city'] ? $data['city'] : null,
                    'zipcode' => $data['postal'] ? $data['postal'] : null,
                    'phoneNo' => $data['phoneNumber'] ? $data['phoneNumber'] : null,
                    'state' => $data['state'] ? $data['state'] : null,
                    'country_id' => $country ? $country->_id : null,
                    'description' => (array_key_exists('special_request', $data) ? $data['special_request'] : (array_key_exists('description', $data) ? $data['description'] : null)),
                    'rfqproduct_of_interest_ids' => (array_key_exists('subject', $data) ? [] : array_unique($listItems->pluck('_id')->toArray())),
                    'inquiry_type_id' => (array_key_exists('subject', $data) ? $lists['lead_inquiry_types'][$data['subject']] : $lists['lead_inquiry_types']['New Product Quote']),
                    'where_learn_product_id' => (array_key_exists('where_learn_product_id', $data) ? $lists['lead_learn_products'][$data['where_learn_product_id']] : null),
                    'whereLearnProduct' => (array_key_exists('whereLearnProduct', $data) ? $data['whereLearnProduct'] : null),
                    'serialNo' => isset($data['serialNo']) ? $data['serialNo'] : null,
                    'model' => isset($data['model']) ? $data['serialNo'] : null,

                    /* Required */
                    'company' => $data['company'],
                    'business_unit_ids' => (array_key_exists('subject', $data) ? ['5b344ae1678f711dfc04ec3f'] : array_unique($businessUnits)),
                    'status_id' => $lists['lead_status']['Open'],
                    'source_id' => $lists['lead_source']['Website'],
                    'type_id' => $lists['lead_type']['End User'],
                    'campaign_id' => $campaign->_id,
                    'from' => 'rfq',
                ]);

                $this->store($request);

                return ['message' => 3];
            }

            return ['message' => 2];
        } else {
            return ['message' => 1];
        }

    }

    public function storeMedicalRFQ(Request $request)
    {

        $data = $this->stripAllTags($request);

        $campaign = Campaign::where('rfqCode', $data['camp_code'])->first();

        $listItems = PickList::where('name', 'lead_rfq_poi')->first()->listItems->whereIn('uniqueText', $data['products']);

        if ($campaign) {

            if ($request->has('g-recaptcha-response')) {
                logDrf($request->all(), 'lead-test-esco-medical');

                $client = new Client();
                $secret = '6LfU-6cUAAAAAEEvGKwvRhasKaSKRer8FVllpjWJ';
                $response = $request['g-recaptcha-response'];
                $ip = $request->ip();
                $response = $client->post('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$response.'&remoteip='.$ip);
                $captcha_data = json_decode($response->getBody(), true);

                if ($captcha_data['success'] === true) {

                    $request = new Request([
                        'firstName' => $data['first_name'],
                        'lastName' => $data['last_name'],
                        'email' => $data['email'],
                        'phoneNo' => $data['number'] ?? $data['pnumber'],
                        'country_id' => $this->convert($data['country'] ?? $data['country_code'], 'Country', 'alpha2Code'),
                        'description' => $data['message'] ?? $data['description'],
                        'rfqproduct_of_interest_ids' => array_unique($listItems->pluck('_id')->toArray()),
                        'timeframe_id' => isset($data['00N90000008GuSf']) ? $this->convert($data['00N90000008GuSf'], 'PickList', 'lead_timeframe') : null,
                        'woid' => '00D90000000qq1v',
                        'inquiry_type_id' => $this->convert($data['subject'] ?? 'New Product Quote', 'PickList', 'lead_inquiry_types'),
                        'medical_survey_id' => $this->convert($data['source'], 'PickList', 'lead_medical_survey', 'uniqueText'),
                        'street' => $data['address'] ?? $data['street'],
                        'city' => 'N/A',
                        'state' => 'N/A',

                        /* Required */
                        'company' => $data['company'],
                        'status_id' => $this->convert('Open', 'PickList', 'lead_status'),
                        'business_unit_ids' => $this->convert('Medical', 'BusinessUnit', 'name'),
                        'source_id' => $this->convert('Website', 'PickList', 'lead_source'),
                        'type_id' => $this->convert('End User', 'PickList', 'lead_type'),
                        'campaign_id' => $campaign->_id,
                        'from' => 'rfq',
                    ]);

                    $this->store($request);

                    return response()->json(['message' => true]);

                } else {
                    return response()->json(['message' => 'Please check the captcha form. Code Error: #002']);
                }

            } else {
                return response()->json(['message' => 'Please check the captcha form. Code Error: #002']);
            }

        } else {
            return response()->json(['message' => 'Error. Failed to submit form request. Please try again.']);
        }

    }

    public function storeVaccixcellRFQ(Request $request)
    {

        $data = $this->stripAllTags($request);

        $campaign = Campaign::where('rfqCode', $data['camp_code'])->first();

        if ($campaign) {

            if ($request->has('g-recaptcha-response')) {
                logDrf($request->all(), 'lead-vaccixcell');

                $client = new Client();
                $secret = '6LfU-6cUAAAAAEEvGKwvRhasKaSKRer8FVllpjWJ';
                $response = $request['g-recaptcha-response'];
                $ip = $request->ip();
                $response = $client->post('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$response.'&remoteip='.$ip);
                $captcha_data = json_decode($response->getBody(), true);

                if ($captcha_data['success'] === true) {

                    $request = new Request([
                        'firstName' => $data['firstName'],
                        'lastName' => $data['lastName'],
                        'email' => $data['email'],
                        'phoneNo' => $data['phone'],
                        'company' => $data['company'],
                        'country_id' => $this->convert($data['country'], 'Country', 'name'),
                        'street' => $data['street'],
                        'zipcode' => $data['postal_code'],
                        'city' => $data['city'],
                        'description' => $data['message'],
                        'status_id' => $this->convert('Open', 'PickList', 'lead_status'),
                        'business_unit_ids' => $this->convert('VacciXcell', 'BusinessUnit', 'name'),
                        'source_id' => $this->convert('Website', 'PickList', 'lead_source'),
                        'type_id' => $this->convert('End User', 'PickList', 'lead_type'),
                        'campaign_id' => $campaign->_id,
                        'from' => 'rfq',
                        'woid' => '00D90000000qq1v',
                        'inquiry_type_id' => $this->convert('New Product Quote', 'PickList', 'lead_inquiry_types'),
                    ]);

                    $this->store($request);

                    return response()->json(['message' => true]);

                } else {
                    return response()->json(['message' => 'Please check the captcha form. Code Error: #002']);
                }

            } else {
                return response()->json(['message' => 'Please check the captcha form. Code Error: #002']);
            }

        } else {
            return response()->json(['message' => 'Error. Failed to submit form request. Please try again.']);
        }

    }

    public function convert($data, $entity, $where, $key = 'value')
    {
        if ($entity == 'PickList') {
            return (new PicklistService)->getIDs($where, $data, null, $key);
        }

        $entity = Entity::where('name', $entity)->first();

        if (is_array($data)) {
            return $entity->getModel()->whereIn($where, $data)->pluck('_id')->toArray();
        } else {
            $model = $entity->getModel()->where($where, $data)->first();

            return $model ? $model->_id : null;
        }
    }
}
