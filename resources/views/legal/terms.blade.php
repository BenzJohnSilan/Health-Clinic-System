@extends('legal.layout')

@section('title', 'Terms and Conditions')
@section('heading', 'Terms and Conditions')

@section('content')
<p class="legal-intro">
    By accessing and using ClinicRMS (Health Clinic Record Management System), users agree to follow these
    Terms and Conditions. They apply to registered patients and authorized clinic personnel who use the system
    to manage patient information, appointments, medical records, and clinic operations.
</p>

<div class="legal-section">
    <h2><span>1</span> Authorized Use</h2>
    <p>
        ClinicRMS is intended for registered patients and authorized clinic personnel, including administrators,
        doctors, and staff. Each user may access only the features and information allowed by their role and
        assigned permissions. Unauthorized access to patient records or restricted system functions is prohibited.
    </p>
</div>

<div class="legal-section">
    <h2><span>2</span> User Responsibilities</h2>
    <p>
        Users must provide accurate information and keep their account details updated. Patients must be at least
        18 years old to register, must upload a valid
        ID during registration, verify their email address with the one-time code sent to them, and wait for
        administrator approval before they can use the patient portal. All users must keep their passwords
        confidential and must not share their accounts.
    </p>
</div>

<div class="legal-section">
    <h2><span>3</span> Prohibited Activities</h2>
    <p>
        Users must not submit false information or a valid ID that is not theirs, use another person's account, access
        records they are not authorized to view, or change or delete information without permission. Interfering with
        system security, uploading harmful files, sending misleading inquiries through the contact form, and using
        ClinicRMS for fraudulent activities are also prohibited.
    </p>
</div>

<div class="legal-section">
    <h2><span>4</span> Accuracy of Information</h2>
    <p>
        Patients must provide correct personal details, contact information, and appointment reasons. They should keep
        their profile updated in Account Settings and inform the clinic if any information needs correction. Accurate
        information helps clinic personnel manage appointments and maintain reliable patient records.
    </p>
</div>

<div class="legal-section">
    <h2><span>5</span> Appointment Rules</h2>
    <p>
        Submitting an appointment request does not guarantee confirmation. Appointments depend on clinic approval,
        doctor availability, and the schedules shown in the system, and the clinic may approve, reject, or reschedule
        a request. Patients should check their appointment status, and an approved appointment can no longer be
        cancelled online within 2 hours of its scheduled time. Walk-in patients are added to the clinic's walk-in
        queue by staff, and waiting time depends on clinic operations.
    </p>
</div>

<div class="legal-section">
    <h2><span>6</span> Medical Records and Clinical Responsibilities</h2>
    <p>
        ClinicRMS supports the management of consultation records, vital signs, diagnoses, treatments, prescriptions,
        medical certificates, and laboratory requests and results. Doctors remain responsible for their clinical
        assessments and medical decisions, while authorized staff perform only the duties assigned to them.
        ClinicRMS does not replace professional medical consultation or emergency medical services.
    </p>
</div>

<div class="legal-section">
    <h2><span>7</span> Billing and Payment</h2>
    <p>
        ClinicRMS records invoices, consultation charges, dispensed medicines, applicable discounts, and payments
        handled by clinic staff. Senior Citizen and PWD discounts are applied by staff according to the system's
        requirements, and only one of these discounts can be used on a single invoice. Payments are recorded as Cash
        or GCash, and a GCash payment requires a unique reference number. ClinicRMS does not process online or card
        payments itself.
    </p>
</div>

<div class="legal-section">
    <h2><span>8</span> System Availability and Limitations</h2>
    <p>
        Temporary interruptions may occur because of maintenance, technical problems, hosting issues, or problems
        with external email and SMS services. Notifications may be delayed or may not arrive, so patients should also
        check their account for updates. The clinic may use alternative procedures to manage appointments and records
        when the system is unavailable.
    </p>
</div>

<div class="legal-section">
    <h2><span>9</span> Account Suspension and Termination</h2>
    <p>
        An administrator may deactivate, reject, or remove an account because of false information, unauthorized
        access, misuse, or changes in personnel access. A deactivated user can no longer sign in, but the account's
        records are kept. Permanently deleting an account may also remove the records linked to it, so the clinic
        should confirm its retention requirements before doing so.
    </p>
</div>

<div class="legal-section">
    <h2><span>10</span> Acceptance of Terms</h2>
    <p>
        Patients must tick the checkbox accepting these Terms and Conditions and the Privacy Policy during
        registration before an account can be created, and this is checked by the system. By creating an account
        and continuing to use ClinicRMS, users accept these Terms and Conditions. Users who do not agree should stop using the system and contact the clinic.
    </p>
</div>
@endsection