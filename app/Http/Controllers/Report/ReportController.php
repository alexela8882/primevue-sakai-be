<?php

namespace App\Http\Controllers\Report;

use App\Builders\ReportTypeBuilder;
use App\Http\Controllers\Controller;
use App\Http\Resources\Field\FieldResource;
use App\Http\Resources\Module\EntityResource;
use App\Http\Resources\Report\ReportFolder;
use App\Http\Resources\Report\ReportResource;
use App\Models\Core\Currency;
use App\Models\Core\Entity;
use App\Models\Core\Field;
use App\Models\Core\Folder;
use App\Models\Report\Report;
use App\Models\Report\ReportEntity;
use App\Services\Folder\FolderService;
use App\Services\ModuleDataCollector;
use App\Services\PicklistService;
use App\Traits\ApiResponseTrait;
use App\Traits\BuilderSearchTrait;

class ReportController extends Controller
{
    use ApiResponseTrait;
    use BuilderSearchTrait;

    protected $filter = [];

    private $clickfilter = [];

    private $user;

    public function __construct(private ModuleDataCollector $mdc, private ReportTypeBuilder $reportTypeBuilder)
    {
        $this->filter = [];
        $this->clickfilter = [];

        $this->user = $this->getEnvironmentUser();
        $this->mdc->setUser($this->user)->setModule('reports');
    }

    public function getTypes()
    {
        if ($this->user) {
            $dataOnly = request('dataonly');
            $widget = request('reportfields');
            $data = [];

            if (! $widget) {
                $folders = Folder::where('type_id', picklist_id('folder_types', 'report_type'))
                    ->orderBy('order', 'asc')->get();
                $data = [
                    'folders' => ReportFolder::collection($folders),
                ];
            }

            if (! $dataOnly) {
                $ent = ReportEntity::all()->pluck('entity_id')->toArray();
                $data['entities'] = EntityResource::collection(Entity::whereIn('_id', $ent)->get());
            }

            return $data;
        }

        return redirect('/');
    }

    public function getFolders()
    {

        $folders = Folder::where('type_id', (new PicklistService)->getIDs('folder_types', 'report'))->get();
        $data = [
            'folders' => ReportFolder::collection($folders),
        ];

        return $data;
    }

    public function showReports($id)
    {
        return $this->respondFriendly(function () use ($id) {
            $data = Report::where('folder_id', $id);
            $f = Field::where('uniqueName', 'report_name');

            if (request()->has('sortField') && request('sortField') != 'undefined') {
                $order = request('sortOrder', 'desc');
                $sField = request('sortField');
                $data = $data->orderBy($sField, $order);
            }

            return ReportResource::collection($data);
        });
    }

    public function index()
    {
        if ($this->user) {
            return $this->respondFriendly(function () {
                $fields = Entity::where('name', 'Report')->first()->getEntityFields();
                $reportFolders = (new FolderService)->getByType('report');

                return [
                    'folders' => ReportFolder::customeCollection($reportFolders, $this->user->_id),
                    'fields' => FieldResource::collection($fields),
                    'currecies' => Currency::get(['code', 'symbol', 'name', 'rate']),
                ];
            });
        }

        return redirect('/');
    }

    protected function getConnectionFields($reportEntities)
    {
        $reportEntities = clone $reportEntities;
        $reportEntities->load('entity.fields');
        $entityNames = $reportEntities->pluck('entity.name');
        $fields = collect([]);
        foreach ($reportEntities as $reportEntity) {
            $curEntity = $reportEntity->entity;
            $fields = $fields->merge($curEntity->fields->filter(function ($field) use ($entityNames, $curEntity) {
                return $field->fieldType->name == 'lookupModel'
                    && $curEntity->name != $field->relation->relatedEntity->name
                    && $entityNames->contains($field->relation->relatedEntity->name);
            }));
        }

        return $fields;
    }
}
