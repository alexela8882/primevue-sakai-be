<?php

namespace Database\Seeders;

use App\Builders\FieldBuilder;
use App\Builders\ModuleBuilder;
use App\Builders\PanelBuilder;
use App\Builders\ViewFilterBuilder;
use App\Models\Company\Branch;
use App\Models\Core\Entity;
use App\Models\Module\Module;
use App\Models\User;
use App\Models\User\Role;
use App\Services\FormulaParser;
use App\Services\PanelService;
use Illuminate\Database\Seeder;

class DataSeeder extends Seeder
{
    public function __construct(private FormulaParser $parser, private FieldBuilder $fieldBuilder, private PanelBuilder $panelBuilder, private ViewFilterBuilder $viewFilterBuilder)
    {

    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $employee = User::where('email', 'alexander.flores@escolifesciences.com')->first();

        $handledRoles = Role::query()
        ->whereIn('name', [
            'crm_service_coordinator',
            'crm_admin'
        ])
        ->get()
        ->pluck('_id')
        ->toArray();

        $handledBranches = Branch::query()
            ->get()
            ->pluck('_id')
            ->toArray();

        $employee->handledBranches()->attach($handledBranches);
        $employee->roles()->attach($handledRoles);
        

        //$this->fieldBuilder->on('SalesOpptItem')->add('lookupModel', idify('units'), 'Units')->relate('many_to_many')->to('Unit', ['serialNo'])->msPopUp()->save();
        // $this->addEntity();
        // $this->addModule();
        // $this->createViewFilters();
        // $this->createPanels();

        $roles = Role::all();
        foreach($roles as $role){
            $this->addPermission($role, 'inquiries');
        }


    }

    public function addModule()
    {
        (new ModuleBuilder)
            ->getFolders()
            ->define('inquiries', 'Inquiry', 'inquiries.index')->decorate('live_help')
            ->underFolder('services', true)
            ->addMainEntity('Inquiry', true)
            ->addPermissions()
            ->addQueries(['owned_and_under', 'owned'])
            ->save();

        $roles = Role::all()->pluck('name')->toArray();

        foreach ($roles as $role) {
            $this->addPermission($role, 'inquiries');
        }

        $roles = Role::all()->pluck('name')->toArray();

        foreach ($roles as $role) {
            $this->addPermission($role, 'inquiries');
        }

    }

    public function addEntity()
    {
        $entities = [
            ['Inquiry', 'App\Models\Customer\Inquiry'],
        ];

        foreach ($entities as $entity) {
            Entity::firstOrCreate(['name' => $entity[0]], [
                'name' => $entity[0],
                'model_class' => $entity[1],
                'repository_class' => null,
                'mutable' => false,
            ]);
        }

        $this->fieldBuilder->on('Inquiry')->add('picklist', 'source_id', 'Source')
            ->enum('inquiry_source', ['Email', 'Phone Call', 'Social Media Platforms', 'Verbal', 'Others'])
            ->required()
            ->ssDropDown()
            ->save();

        $this->fieldBuilder->on('Inquiry')->add('date', 'inquiryDate', 'Date Inquired')->required()->save();
        $this->fieldBuilder->on('Inquiry')->add('text', 'subject', 'Subject')->required()->save();
        $this->fieldBuilder->on('Inquiry')->add('text', 'remarks', 'Remarks')->required()->save();

        $this->fieldBuilder->on('Inquiry')->add('text', 'accountName', 'Company Name')->save();

        $this->fieldBuilder->on('Inquiry')->add('text', 'contactPersonName', 'Contact Person Name')->save();
        $this->fieldBuilder->on('Inquiry')->add('text', 'phoneNo', 'Contact No')->save();
        $this->fieldBuilder->on('Inquiry')->add('text', 'email', 'Email')->save();

        $filter = 'where("Employee::_id", "=", "Role::user_id")->where("Employee::_id", "in", "currentUser::people")';
        $this->fieldBuilder->on('Inquiry')->add('lookupModel', idify('owner'), 'Opportunity Owner')->relate('one_from_many')->to('Employee', [['firstName', 'lastName'], ['firstName', 'lastName', 'email']])->filterQuery($filter)->ssPopUp()->save();

        //$this->fieldBuilder->on('Inquiry')->add('lookupModel', idify('account'), 'Account Name')->relate('one_from_many')->to('Account', [['name'],['name','owner_id']])->includeFields(['isEscoBranch'])->ssPopUp()->save();

        $this->fieldBuilder->on('Inquiry')->addUserStamps();
    }

    public function addPermission($role, $moduleName)
    {
        //$admin = Role::where('name', $role)->first();
        Module::where('name', $moduleName)
            ->first()->permissions()->each(function ($permission) use ($role) {
                $permission->roles()->attach([$role->_id]);
            });
    }

    protected function createPanels()
    {

        (new PanelService)->deletePanelByModule('inquiries');

        $this->panelBuilder->on('inquiries')->atIndex(1);

        $this->panelBuilder->addSection('Contact Information', [
            [
                'accountName',
                'contactPersonName',
                'phoneNo',
                'email',
            ],

            [
                'inquiryDate',
                'source_id',
                'owner_id',
            ],
        ]);

        $this->panelBuilder->addSection('Inquiry Details', [
            [
                'subject',
                'remarks',
            ],
        ]);
        $this->panelBuilder->addSection(null, [
            [
                'created_by',
                'created_at',
            ],
            [
                'updated_by',
                'updated_at',
            ],
        ])->save();

    }

    public function createViewFilters()
    {
        $fields = [
            'inquiryDate',
            'accountName',
            'contactPersonName',
            'email',
            'phoneNo',
            'subject',
        ];

        $this->viewFilterBuilder
            ->on('inquiries')
            ->add("My Team's Inquiries", $fields, false)
            ->query('Inquiry:owned_and_under');

        $this->viewFilterBuilder
            ->add('My Inquiries', $fields, true)
            ->query('Inquiry:owned')
            ->save();

    }
}
