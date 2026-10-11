<?php

/*
|--------------------------------------------------------------------------
| Billing — invoice item discount types
|--------------------------------------------------------------------------
|
| Controlled list used by Staff → Billing & Payments. "None" is not listed:
| an item with no discount_type simply has no discount.
|
|  - default_rate   : the percentage applied when the type is selected.
|  - rate_editable  : false -> the server ALWAYS uses default_rate (the
|                     client-submitted rate is ignored), so a statutory
|                     discount can't be tampered with from the browser.
|                     true  -> Staff enters an authorized percentage (0-100).
|
| To change the statutory rate later, change default_rate here only.
| A discount applies to ONE invoice item at a time and never stacks:
| each item has a single discount_type.
*/

return [

    'discount_types' => [
        'senior_citizen' => [
            'label'         => 'Senior Citizen',
            'default_rate'  => 20.00,
            'rate_editable' => false,
        ],
        'pwd' => [
            'label'         => 'PWD',
            'default_rate'  => 20.00,
            'rate_editable' => false,
        ],
        'clinic_discount' => [
            'label'         => 'Clinic Discount',
            'default_rate'  => null,
            'rate_editable' => true,
        ],
        'other_authorized' => [
            'label'         => 'Other Authorized Discount',
            'default_rate'  => null,
            'rate_editable' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Consultation fee waiver reasons
    |--------------------------------------------------------------------------
    |
    | The ONLY reasons a Doctor may give when waiving the Consultation fee
    | (Charges & Completion -> "Fee Waiver / No Charge"). The key is what is
    | stored on the invoice line (invoice_items.waiver_reason); the server
    | rejects anything not listed here. `notes_required` forces an explanation
    | (stored in invoice_items.waiver_notes) — used by "Other".
    |
    | A waiver always covers exactly the Consultation line (100 %); it is never
    | an arbitrary amount and it never affects medicines or other services.
    */
    'waiver_reasons' => [
        'courtesy' => [
            'label'          => 'Doctor/Clinic Courtesy',
            'notes_required' => false,
        ],
        'follow_up' => [
            'label'          => 'Follow-up Consultation',
            'notes_required' => false,
        ],
        'financial_hardship' => [
            'label'          => 'Financial Hardship',
            'notes_required' => false,
        ],
        'other' => [
            'label'          => 'Other',
            'notes_required' => true,
        ],
    ],

];
