<?php

namespace Database\Seeders;

use App\Builders\FieldBuilder;
use App\Builders\ModuleBuilder;
use App\Models\Core\Entity;
use App\Models\Module\Module;
use App\Models\User\Role;
use App\Services\FormulaParser;
use Illuminate\Database\Seeder;


class DataSeeder extends Seeder
{
    public function __construct(private FormulaParser $parser, private FieldBuilder $fieldBuilder)
    {

    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $this->fieldBuilder->on('SalesOpptItem')->add('lookupModel', idify('units'), 'Units')->relate('many_to_many')->to('Unit', ['serialNo'])->msPopUp()->save();
        $this->addEntity();
        $this->addModule();

    }

    public function addModule()
    {
        (new ModuleBuilder)
            ->getFolders()
            ->define('inquiries', 'Inquiry', 'inquiries.index')->decorate('live_help')
            ->underFolder('services', true)
            ->addMainEntity('Inquiry', true)
            ->addPermissions()
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

        $this->fieldBuilder->on('Inquiry')->add('text', 'name', 'Name')->save();
        $this->fieldBuilder->on('Inquiry')->add('text', 'phoneNo', 'Contact No')->save();
        $this->fieldBuilder->on('Inquiry')->add('text', 'email', 'Email')->save();

        $filter = 'where("Employee::_id", "=", "Role::user_id")->where("Employee::_id", "in", "currentUser::people")';
        $this->fieldBuilder->on('Inquiry')->add('lookupModel', idify('owner'), 'Opportunity Owner')->relate('one_from_many')->to('Employee', [['firstName', 'lastName'], ['firstName', 'lastName', 'email']])->filterQuery($filter)->ssPopUp()->save();
     


        // $this->fieldBuilder->on('Inquiry')->add('lookupModel', idify('account'), 'Account Name')->relate('one_from_many')->to('Account', [['name'],['name','owner_id']])->includeFields(['isEscoBranch'])->ssPopUp()->save();
         
        $this->fieldBuilder->on('Inquiry')->addUserStamps();
    }

    public function addPermission($role, $moduleName)
    {
        $admin = Role::where('name', $role)->first();
        Module::where('name', $moduleName)
            ->first()->permissions()->each(function ($permission) use ($admin) {
                $permission->roles()->attach([$admin->_id]);
            });
    }
}
