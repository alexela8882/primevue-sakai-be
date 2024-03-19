<?php

namespace Database\Seeders;

use App\Models\Core\Field;
use Illuminate\Database\Seeder;

class FormulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $this->convertFilter();

    }

    public function convertFilter()
    {

        $case = [
            [[
                ['salesPrice', '==', 0],
            ], 0],

            [[
                ['branch_id', '==', '5badf748678f7111186ba26e'],
            ], 'ROUND(salesPrice - salesPrice * discount,0) * quantity'],
            [[
                ['branch_id', 'in', ['5bfcf6c9678f71594d642a86', '5badf748678f7111186ba268']],
            ], '((salesPrice - (salesPrice * discount)) + ((salesPrice - (salesPrice * discount)) * vat)) * quantity'],

            [[
                ['branch_id', '==', '5c6a88e3a6ebc728735b9db2'],
            ], '(salesPrice - salesPrice * (discount + commisionPercentDistributor)) * quantity'],
        ];

        $default = '(salesPrice - salesPrice * discount) * quantity';

        $this->saveFilter('salesopptitem_sub_total', $case, $default);

        $case = [
            [[
                ['amount', '==', 0],
            ], 0],

            [[
                ['branch_id', '==', '5db153f7a6ebc7c02b0c8d02'],
            ], 'amount + vat'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'MMK'],
            ], 'amount + vat'],

            [[
                ['branch_id', '==', '5bfcf6c8678f71594d642a84'],
            ], 'amount + vat'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', '"EURO - w/VAT"'],
            ], 'vat + idrDelivery + (amount - discount)'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'TWD'],
            ], 'ROUND(vat + amount,0)'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'USD-Myanmar'],
            ], 'amount + vat'],

            [[
                ['branch_id', '==', '5bfcf6c9678f71594d642a86'],
            ], 'vat + cad + transportCost + bgdLetterOfCredit + bgdCertCountryOrigin + idrDelivery + bgdTeleTransfer + (amount - discount)'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', ['BDT-SP', 'PHP']],
            ], 'amount + vat'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'VND'],
            ], 'vatAmountTotal + subTotalNoVat'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'SGD'],
            ], 'vat + (amount - (amount * discountPercentage))'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'THB'],
            ], 'vat + amount'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'EURO'],
            ], 'usShippingAndHandling + idrDelivery + (amount - discount)'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'BD-USD'],
            ], ' cad + bgdCertCountryOrigin + bgdLetterOfCredit + bgdTeleTransfer + idrDelivery + amount'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', ['HKD', 'MYR']],
            ], 'amount - discount'],

            [[
                ['pricebook_id', '==', '5c0723d8678f7161775a1e10'],
            ], 'amount + vat'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'USD'],
            ], 'usSalesTax + usInsideDelivery + liftGateCharge + usShippingDock + amount'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'IDR'],
                ['idr_vat_id', 'ISPICKVAL', 'VAT INC'],
            ], '(amount - discount) + vat + idrDelivery'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'IDR'],
            ], '(amount - discount) + idrDelivery'],

        ];

        $default = 'amount + 0';

        $this->saveFilter('salesquote_grand_total_all', $case, $default);

        $case = [
            [[
                ['amount', '==', 0],
            ], 0],
            [[
                ['currency_picklist_id', 'ISPICKVAL', 'IDR'],
            ], 'amount + idrDelivery'],
        ];

        $default = 0;
        $this->saveFilter('salesquote_grand_total_idr', $case, $default);

        $case = [
            [[
                ['amount', '==', 0],
            ], 0],

            [[
                ['branch_id', '==', '5db153f7a6ebc7c02b0c8d02'],
                ['bdt_vat_id', 'ISPICKVAL', 'Service (GST 18%)'],
            ], 'amount * 0.18'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'MYR'],
            ], 0],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'MMK'],
            ], 'amount*0.05'],

            [[
                ['branch_id', '==', '5bfcf6c8678f71594d642a84'],
                ['idr_vat_id', 'ISPICKVAL', 'VAT INC'],
            ], 'amount*0.05'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'VND'],
                ['bdt_vat_id', 'ISPICKVAL', 'Vat (10%)'],
            ], 'amount*0.1'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'VND'],
                ['bdt_vat_id', 'ISPICKVAL', 'Vat (8%)'],
            ], 'amount*0.08'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'VND'],
                ['bdt_vat_id', 'ISPICKVAL', 'Vat (5%)'],
            ], 'amount*0.05'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'VND'],
            ], 'amount*0.1'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'USD-Myanmar'],
            ], 'amount*0.05'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'TWD'],
            ], 'ROUND(amount*0.05,1)'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'BDT'],
                ['bdt_vat_id', 'ISPICKVAL', 'Spare Parts (5%)'],
            ], '(amount-discount)*0.05'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'BDT'],
                ['bdt_vat_id', 'ISPICKVAL', 'Validation, Certification (15%)'],
            ], '(amount-discount)*0.15'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'BDT'],
                ['bdt_vat_id', 'ISPICKVAL', 'Other general service (IQOQ) (10%)'],
            ], '(amount-discount)*0.10'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'BDT'],
                ['bdt_vat_id', 'ISPICKVAL', 'VAT% (7.5%)'],
            ], '(amount-discount)*0.075'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'BDT'],
                ['bdt_vat_id', 'ISPICKVAL', 'Vat (0%)'],
            ], 0],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'BDT'],
            ], 0],

            [[
                ['pricebook_id', '==', '5c0723d8678f7161775a1e10'],
            ], 'amount*0.15'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'BDT-SP'],
            ], 'amount*0.04'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'PHP'],
            ], 'amount*0.12'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'SGD'],
            ], '(amount - (amount * discountPercentage))*0.08'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'THB'],
            ], 'amount*0.07'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'IDR'],
            ], '(amount-discount)*0.11'],

            [[
                ['currency_picklist_id', 'ISPICKVAL', 'EURO-w/VAT'],
            ], 'amount*0.2'],

        ];
        $default = 'amount + 0';

        $this->saveFilter('salesquote_vat', $case, $default);

    }

    private function saveFilter($uniqueName, $case, $default)
    {
        $data = [];
        $formulaField = Field::where('uniqueName', $uniqueName)->first();

        if (! $formulaField) {
            dump($uniqueName);

            return null;
        }

        foreach ($case as $key => $value) {
            $filters = $value[0];
            $transformedFilter = [];
            foreach ($filters as $filter) {

                $field = Field::where('entity_id', $formulaField->entity_id)->where('name', $filter[0])->first();

                $v = $filter[2];

                if ($filter[1] == 'ISPICKVAL') {
                    $v = picklist_id($field->listName, $filter[2]);
                }

                $d = [
                    'field' => $field->_id,
                    'operand' => '==',
                    'value' => $v,
                ];

                $transformedFilter[] = $d;

            }

            $data[] = [
                'order' => $key,
                'filters' => $transformedFilter,
                'resultValue' => $value[1],
            ];
        }

        $formulaField->update(['formulaFilter' => ['conditions' => $data, 'default' => $default]]);

    }
}
