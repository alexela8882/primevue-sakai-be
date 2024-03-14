<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Company\Campaign;
use App\Models\Core\Entity;
use App\Models\Core\Picklist;
use App\Models\Customer\Lead;
use App\Services\ModuleDataCollector;
use App\Services\PicklistService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class LeadController extends Controller
{
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

    public function getCleanRequest($request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                $data[$key] = strip_tags($value);
            }
        }

        return new Request($data);
    }

    public function getRFQ(Request $request)
    {
        $cleanRequest = $this->getCleanRequest($request);
        $campaign = Campaign::where('rfqCode', $cleanRequest['camp_code'])->first();

        if ($campaign) {
            if ($request->has('g-recaptcha-response') && $campaign->name != 'Website - Medical RFQ Form') {
                logDrf($request->all(), 'lead-regional');

                $client = new Client();
                $secret = '6LfU-6cUAAAAAEEvGKwvRhasKaSKRer8FVllpjWJ';
                $response = $request['g-recaptcha-response'];
                $ip = $request->ip();
                $response = $client->post('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$response.'&remoteip='.$ip);
                $data = json_decode($response->getBody(), true);

                if ($data['success'] === true) {
                    $poi = explode(',', $cleanRequest['00N90000071POI']);
                    $units = array_map(function ($value) {
                        if ($value == 'Lab') {
                            return 'Scientific';
                        }

                        return $value;
                    }, explode(';', $cleanRequest['00N90000008GucA']));

                    $listItems = Picklist::where('name', 'lead_rfq_poi')->first()->listItems->filter(function ($listItem) use ($poi) {
                        foreach ($poi as $value) {
                            if ($value == $listItem->uniqueText) {
                                return true;
                            } elseif (in_array($value, $listItem->old_value ?? [])) {
                                return true;
                            }
                        }

                        return false;
                    });

                    if ($request->exists('00N90000007htp')) {
                        if ($request->get('00N90000007htp') == 'http://') {
                            $website = null;
                        } else {
                            $website = $request->get('00N90000007htp');
                        }
                    } elseif ($request->exists('URL')) {
                        $website = $request->get('URL');
                    } else {
                        $website = null;
                    }

                    $request = new Request([
                        'firstName' => $cleanRequest['first_name'],
                        'lastName' => $cleanRequest['last_name'],
                        'email' => $cleanRequest['email'],
                        'phoneNo' => $cleanRequest['00N90000008lwMp'],
                        'website' => $website,
                        'company' => $cleanRequest['company'],
                        'country_id' => $this->convert($cleanRequest['country_code'], 'Country', 'alpha2Code'),
                        'source_id' => $this->convert($cleanRequest['lead_source'], 'PickList', 'lead_source'),
                        'type_id' => $this->convert($cleanRequest['00N90000009imdc'], 'PickList', 'lead_type'),
                        'status_id' => $this->convert('Open', 'PickList', 'lead_status'),
                        'campaign_id' => $campaign->_id,
                        'business_unit_ids' => $this->convert($units, 'BusinessUnit', 'name'),
                        'rfqproduct_of_interest_ids' => $listItems->pluck('_id')->toArray(),
                        'description' => $cleanRequest['description'],
                        'created_at' => date('Y-m-d'),
                        'updated_at' => date('Y-m-d'),
                        'from' => 'rfq',
                        'woid' => '00D90000000qq1v',
                        'city' => $cleanRequest['city'],
                        'street' => $cleanRequest['street'],
                        'state' => $cleanRequest['state'],
                        'zipcode' => $cleanRequest['zipcode'],
                        'inquiry_type_id' => $this->convert('New Product Quote', 'PickList', 'lead_inquiry_types'),
                    ]);

                    $this->store($request);

                    return response()->json(['message' => true]);
                } else {
                    return response()->json(['message' => 'Please check the captcha form. Code Error: #002']);
                }
            } elseif ($campaign->name === 'Website - VacciXcell RFQ') {
                logDrf($request->all(), 'lead-vaccixcell');

                $request = new Request([
                    'firstName' => $cleanRequest['firstName'],
                    'lastName' => $cleanRequest['lastName'],
                    'email' => $cleanRequest['email'],
                    'phoneNo' => $cleanRequest['phone'],
                    'company' => $cleanRequest['company'],
                    'country_id' => $this->convert($cleanRequest['country'], 'Country', 'name'),
                    'street' => $cleanRequest['street'],
                    'zipcode' => $cleanRequest['postal_code'],
                    'city' => $cleanRequest['city'],
                    'description' => $cleanRequest['message'],
                    'status_id' => $this->convert('Open', 'PickList', 'lead_status'),
                    'business_unit_ids' => $this->convert('VacciXcell', 'BusinessUnit', 'name'),
                    'source_id' => $this->convert('Website', 'PickList', 'lead_source'),
                    'type_id' => $this->convert('End User', 'PickList', 'lead_type'),
                    'campaign_id' => $campaign->_id,
                    'created_at' => date('Y-m-d'),
                    'updated_at' => date('Y-m-d'),
                    'from' => 'rfq',
                    'woid' => '00D90000000qq1v',
                    'inquiry_type_id' => $this->convert('New Product Quote', 'PickList', 'lead_inquiry_types'),
                ]);

                $this->store($request);

                return response()->json(['message' => true]);
            } elseif ($campaign->name === 'Website - Esco Aster RFQ') {
                logDrf($request->all(), 'lead-escoaster');

                $request = new Request([
                    'firstName' => $cleanRequest['firstName'],
                    'lastName' => $cleanRequest['lastName'],
                    'email' => $cleanRequest['email'],
                    'phoneNo' => $cleanRequest['phone'],
                    'company' => $cleanRequest['company'],
                    'country_id' => $this->convert($cleanRequest['country'], 'Country', 'name'),
                    'description' => $cleanRequest['message'],
                    'status_id' => $this->convert('Open', 'PickList', 'lead_status'),
                    'business_unit_ids' => $this->convert('Esco Aster', 'BusinessUnit', 'name'),
                    'source_id' => $this->convert('Website', 'PickList', 'lead_source'),
                    'type_id' => $this->convert('End User', 'PickList', 'lead_type'),
                    'campaign_id' => $campaign->_id,
                    'created_at' => date('Y-m-d'),
                    'updated_at' => date('Y-m-d'),
                    'from' => 'rfq',
                    'woid' => '00D90000000qq1v',
                    'inquiry_type_id' => $this->convert('New Product Quote', 'PickList', 'lead_inquiry_types'),
                ]);

                $this->store($request);

                return response()->json(['message' => true]);
            } elseif ($campaign->name === 'Website - Medical RFQ Form') {
                logDrf($request->all(), 'lead-test-esco-medical');

                $poi = explode(',', $cleanRequest['00N90000071POI']);

                $request = new Request([
                    'rfqproduct_of_interest_ids' => $this->convert($cleanRequest['products'] ?? $poi, 'PickList', 'lead_rfq_poi', 'uniqueText'),
                    'firstName' => $cleanRequest['first_name'],
                    'lastName' => $cleanRequest['last_name'],
                    'email' => $cleanRequest['email'],
                    'phoneNo' => $cleanRequest['number'] ?? $cleanRequest['pnumber'],
                    'company' => $cleanRequest['company'],
                    'country_id' => $this->convert($cleanRequest['country'] ?? $cleanRequest['country_code'], 'Country', 'alpha2Code'),
                    'description' => $cleanRequest['message'] ?? $cleanRequest['description'],
                    'status_id' => $this->convert('Open', 'PickList', 'lead_status'),
                    'business_unit_ids' => $this->convert('Medical', 'BusinessUnit', 'name'),
                    'source_id' => $this->convert('Website', 'PickList', 'lead_source'),
                    'type_id' => $this->convert('End User', 'PickList', 'lead_type'),
                    'timeframe_id' => $this->convert($cleanRequest['00N90000008GuSf'], 'PickList', 'lead_timeframe'),
                    'campaign_id' => $campaign->_id,
                    'created_at' => date('Y-m-d'),
                    'updated_at' => date('Y-m-d'),
                    'from' => 'rfq',
                    'woid' => '00D90000000qq1v',
                    'inquiry_type_id' => $this->convert($cleanRequest['subject'] ?? 'New Product Quote', 'PickList', 'lead_inquiry_types'),
                    'medical_survey_id' => $this->convert($cleanRequest['source'], 'PickList', 'lead_medical_survey', 'uniqueText'),
                    'street' => $cleanRequest['address'] ?? $cleanRequest['street'],
                    'city' => 'N/A',
                    'state' => 'N/A',
                ]);

                $this->store($request);

                return response()->json(['message' => true]);
            }

            $this->reportLeadError($request);

            return response()->json(['message' => 'Please check the captcha form. Code Error: #001']);
        }

        return response()->json(['message' => 'Error. Failed to submit form request. Please try again.']);
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
