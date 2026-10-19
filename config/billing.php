<?php

/*
|--------------------------------------------------------------------------
| Billing — invoice item discount types
|--------------------------------------------------------------------------
|
| Controlled list used by Staff → Billing & Payments. "None" is not listed:
| an item with no discount_type simply has no discount.
|
|  - default_rate  : the percentage applied when the type is selected.
|  - rate_editable  : false -> the server ALWAYS uses default_rate (the
|                     client-submitted rate is ignored), so a statutory
|                     discount can't be tampered with from the browser.
|                     true  -> Staff enters an authorized percentage (0-100).
|                     (No type uses this right now — only the two statutory
|                     discounts below are offered.)
|
| To change the statutory rate later, change default_rate here only.
| A discount applies to ONE invoice item at a time and never stacks:
| each item has a single discount_type.
*/

return [

    /*
    | Legal basis (checked against the statute texts — see the audit report):
    |  - Senior Citizen: RA 9994 (Expanded Senior Citizens Act of 2010), Sec. 4(a):
    |    20 % discount (+ VAT exemption "if applicable") on medicines, medical and
    |    dental services, diagnostic/laboratory fees and the professional fees of
    |    attending physicians in private hospitals, medical facilities and
    |    outpatient clinics — for the senior citizen's own use. A senior citizen
    |    is 60 years old or above (hence `min_age`).
    |  - PWD: RA 10754 (amending RA 7277 Sec. 32): same 20 % + VAT exemption on
    |    medicines and on medical/dental services and doctors' professional fees
    |    in private medical facilities, for the PWD's own use, on proof of a
    |    valid PWD ID.
    |  - Neither can be combined with another discount programme for the same
    |    purchase (hence: one discount per item, one kind per invoice).
    |
    | NOT decided here — confirm with the clinic's accountant / a Philippine tax
    | professional before relying on them:
    |  - whether VAT exemption / BIR invoice requirements apply to this clinic
    |    (this system does not compute tax);
    |  - which lines count as discountable (e.g. is a Medical Certificate fee a
    |    "medical service"?). Today every normal line can be discounted; the
    |    Doctor's consultation waiver is separate and can't be edited by Staff.
    |
    | requires_id : Staff must type the patient's ID number; it is saved on the
    |               invoice with who entered it and when.
    | min_age     : refuse the discount when the patient's birthdate on file
    |               shows a younger age (only checked when a birthdate exists).
    */
    'discount_types' => [
        'senior_citizen' => [
            'label'         => 'Senior Citizen',
            'default_rate'  => 20.00,
            'rate_editable' => false,
            'requires_id'   => true,
            'id_label'      => 'Senior Citizen ID No.',
            'min_age'       => 60,
        ],
        'pwd' => [
            'label'         => 'PWD',
            'default_rate'  => 20.00,
            'rate_editable' => false,
            'requires_id'   => true,
            'id_label'      => 'PWD ID No.',
        ],
    ],

    // 4–60 characters: letters, numbers, spaces, dashes, slashes.
    'id_number_pattern' => '/^[A-Za-z0-9][A-Za-z0-9\-\/ ]{3,59}$/',

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