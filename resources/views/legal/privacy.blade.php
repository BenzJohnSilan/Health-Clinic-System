@extends('legal.layout')

@section('title', 'Privacy Policy')
@section('heading', 'Privacy Policy')

@section('content')
<p class="legal-intro">
    ClinicRMS is a Health Clinic Record Management System used to manage patient information, appointments,
    medical records, prescriptions, laboratory results, and billing. This Privacy Policy explains how information
    is collected, used, stored, and protected in connection with the system, in line with the Data Privacy Act of
    2012 (Republic Act No. 10173) of the Philippines.
</p>

<div class="legal-section">
    <h2><span>1</span> Collection of Information</h2>
    <p>
        During registration, ClinicRMS collects your name, birthdate, gender, civil status, address, contact number,
        email address, username, password, ID type, and a photo of your valid ID. As the system is used, it also
        handles appointment details, vital signs, diagnoses, treatments, prescriptions, medical certificates,
        laboratory requests and results, invoices, discounts, payments, and activity logs. Details of walk-in
        patients are entered by clinic staff, and inquiries sent through the website contact form include your name,
        email, contact number, and message.
    </p>
</div>

<div class="legal-section">
    <h2><span>2</span> Purpose of Data Collection</h2>
    <p>
        Information is used to verify registrations, manage accounts, schedule appointments, organize the walk-in
        queue, maintain medical records, document consultations, manage prescriptions and laboratory results, and
        record billing and payments. Your email address is used to send verification codes and account notices, and
        your contact number may be used for appointment and laboratory-related SMS notices, depending on the clinic's
        notification settings. Contact form details are used to reply to your inquiry.
    </p>
</div>

<div class="legal-section">
    <h2><span>3</span> Data Protection and Security</h2>
    <p>
        ClinicRMS stores passwords in hashed form, requires email verification with a one-time code that expires in
        10 minutes, and temporarily locks sign-in after repeated failed attempts. Access is controlled by user role
        and permissions, valid ID images are kept in private storage with no public link, and user activity is
        recorded in activity logs. The clinic also runs scheduled system backups that are stored in a non-public
        location. These measures help protect your information, but no system can guarantee absolute security.
    </p>
</div>

<div class="legal-section">
    <h2><span>4</span> Data Confidentiality</h2>
    <p>
        Patients can view only their own appointments, records, billing, and notifications. Doctors work with the
        patient information needed for their consultations, and staff can use only the functions the administrator
        has assigned to them, so a staff member with a billing-only role does not see medical records. Administrators
        manage accounts, permissions, and system settings, and only administrators can open uploaded valid IDs.
        Clinic personnel must use patient information only for their authorized duties.
    </p>
</div>

<div class="legal-section">
    <h2><span>5</span> Data Retention and Disposal</h2>
    <p>
        Information is kept only as long as needed for legitimate clinic purposes and for legal or professional
        record-keeping requirements. Deactivating an account does not delete its records, while permanently deleting
        an account also removes the appointments, medical records, billing records, and other data linked to it.
        Older backup files are removed automatically once the number of backups to keep is exceeded. Records are kept for the period required by law and clinic policy, and are disposed of securely once they are no longer needed.
    </p>
</div>

<div class="legal-section">
    <h2><span>6</span> Rights of Data Subjects</h2>
    <p>
        Under the Data Privacy Act of 2012, you have the right to be informed about how your data is processed, to
        access and correct your personal information, to object to certain processing, to request the deletion or
        blocking of your data when applicable, to data portability where applicable, to claim damages, and to file
        a complaint with the National Privacy Commission. These rights are subject to the conditions and exceptions
        provided by law, including the clinic's duty to keep medical and billing records.
    </p>
</div>

<div class="legal-section">
    <h2><span>7</span> Third-Party Data Processing</h2>
    <p>
        ClinicRMS relies on outside services to work. The system is hosted on Hostinger, SMS notices are sent through
        iProgSMS (which receives the recipient's contact number and the message), and emails are sent through the
        clinic's email service. The website also loads fonts, icons, a date picker, and an embedded Google Map from external sites,
        which may receive your browser's IP address. Information is shared with these providers only as needed to
        operate the service.
    </p>
</div>

<div class="legal-section">
    <h2><span>8</span> Privacy Requests and Complaints</h2>
    <p>
        Registered users can review and update their details in Account Settings. To request access to or correction
        of your information, or to raise a privacy concern, send a message through the Contact form on the clinic's
        website or speak with the clinic's front desk. Requests and concerns will be handled by the clinic's authorized personnel.
    </p>
</div>

<div class="legal-section">
    <h2><span>9</span> Privacy and Legal Compliance</h2>
    <p>
        Personal information must be processed only for legitimate and proper purposes and handled according to
        applicable data protection requirements. This policy describes the system's current practices and does not by
        itself guarantee compliance with the law. For official guidance or to file a complaint, visit the National
        Privacy Commission at
        <a href="https://privacy.gov.ph/" target="_blank" rel="noopener noreferrer">https://privacy.gov.ph/</a>.
    </p>
</div>
@endsection