<?php

namespace App\Support;

/**
 * The landing page content exactly as it was hardcoded in
 * resources/views/landing.blade.php before Website Management existed.
 *
 * It serves two purposes:
 *  1. Fallback — any setting the Admin has never saved falls back to the
 *     value here, so the public page renders even on an empty database.
 *  2. Starting content — the landing_items migration copies the item lists
 *     into the database the first time, so the Admin edits the real
 *     current content instead of starting from a blank list.
 *
 * Text fields accept **bold** markup (see LandingText); nothing else.
 */
class LandingDefaults
{
    /** Sections that are lists of repeatable items (landing_items.section). */
    public const SECTIONS = ['services', 'features', 'steps', 'workflow', 'faqs'];

    /** FAQ tab keys => tab label. Layout is fixed; Admin only picks one per FAQ. */
    public const FAQ_CATEGORIES = [
        'appointments' => 'Appointments',
        'account'      => 'Account & Login',
        'records'      => 'Medical Records',
        'clinic'       => 'Clinic Info',
    ];

    /** Single-value settings, grouped by the Admin tab/form that edits them. */
    public static function settings(): array
    {
        return [
            // ---- Hero ----
            'hero_badge'            => 'Secure Patient Portal',
            'hero_title_line1'      => 'Your Health,',
            'hero_title_highlight'  => 'Our Priority',
            'hero_description'      => "Schedule appointments and access your medical records at any time through our clinic's secure online patient portal.",
            'hero_primary_label'    => 'Create an Account',
            'hero_primary_url'      => '',   // empty = Register page
            'hero_secondary_label'  => 'About the Clinic',
            'hero_secondary_url'    => '',   // empty = #about
            'hero_image'            => '',

            // ---- About ----
            'about_label'           => 'Who We Are',
            'about_title'           => 'About Our',
            'about_title_highlight' => 'Clinic',
            'about_text_1'          => 'Our clinic is committed to delivering accessible, well-organized healthcare services to the community. Through the online patient portal, patients can schedule appointments and securely access their health records in one place.',
            'about_text_2'          => "Whether you are a patient or a member of our clinical team, the system provides a secure and efficient experience tailored to your role, with access limited to the information relevant to you.",
            'about_image'           => '',

            // ---- Section headings ----
            'services_label'        => 'What We Offer',
            'services_title'        => 'Our Services',
            'services_sub'          => 'Dependable, well-organized healthcare services supported by our online patient portal.',
            'features_label'        => 'Your Account',
            'features_title'        => 'What You Can Do In The Patient Portal',
            'features_sub'          => 'Once your account has been approved, you can manage your care online through your personal dashboard.',
            'steps_label'           => 'Getting Started',
            'steps_title'           => 'How It Works',
            'steps_sub'             => 'Gaining access to your patient account involves four simple steps.',
            'workflow_label'        => 'Behind The Scenes',
            'workflow_title'        => 'How Your Appointment Is Handled',
            'workflow_sub'          => 'A clear and transparent process from appointment request to completed visit.',
            'faqs_label'            => 'Got Questions?',
            'faqs_title'            => 'Frequently Asked Questions',
            'faqs_sub'              => "Find answers to common questions about our clinic and patient portal. If you need further assistance, our virtual assistant, Viora, is available to help.",

            // ---- Contact ----
            'contact_label'         => 'Get In Touch',
            'contact_title'         => 'Contact Us',
            'contact_sub'           => "For inquiries or support, please contact us and our team will respond as soon as possible.",
            'contact_address'       => "Barangay Dayap, Calauan,\nLaguna, Philippines",
            'contact_phones'        => "+63 917 123 4567\n+63 2 8123 4567",
            'contact_emails'        => "support@clinicrms.com\nadmin@clinicrms.com",

            // ---- Clinic information / location ----
            'location_label'        => 'Find Us',
            'location_title'        => 'Clinic Information',
            'location_sub'          => "Our clinic is conveniently located and easy to reach.",
            'location_address'      => "Barangay Dayap, Calauan,\nLaguna 4012, Philippines",
            'location_directions'   => 'From Santa Cruz, take a jeepney or bus bound for Calauan. Alight at Calauan town proper, then take a tricycle to Barangay Dayap.',
            'location_parking'      => 'Free parking is available within the clinic compound for patients and visitors.',
            'location_hours'        => "Mon–Fri: 9:00 AM – 5:00 PM\nSaturday: 9:00 AM – 12:00 PM\nSunday: Closed",
            'location_map_url'      => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.1!2d121.3167!3d14.1500!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd5b2e2e2e2e2f%3A0x0!2sBrgy.+Dayap%2C+Calauan%2C+Laguna!5e0!3m2!1sen!2sph!4v1',

            // ---- Footer / brand ----
            'brand_name'            => 'ClinicRMS',
            'footer_description'    => "The official online patient portal of our clinic for managing appointments and medical records securely.",
            'footer_address'        => 'Barangay Dayap, Calauan, Laguna 4012, Philippines',
            'footer_phone'          => '+63 917 123 4567',
            'footer_email'          => 'support@clinicrms.com',
            'footer_copyright_name' => 'Clinic Record System',
            'footer_privacy_url'    => '#',
            'footer_terms_url'      => '#',
        ];
    }

    /** Which setting keys belong to which Admin form. */
    public static function groups(): array
    {
        return [
            'hero'     => ['hero_badge', 'hero_title_line1', 'hero_title_highlight', 'hero_description', 'hero_primary_label', 'hero_primary_url', 'hero_secondary_label', 'hero_secondary_url'],
            'about'    => ['about_label', 'about_title', 'about_title_highlight', 'about_text_1', 'about_text_2'],
            'services' => ['services_label', 'services_title', 'services_sub'],
            'features' => ['features_label', 'features_title', 'features_sub'],
            'steps'    => ['steps_label', 'steps_title', 'steps_sub'],
            'workflow' => ['workflow_label', 'workflow_title', 'workflow_sub'],
            'faqs'     => ['faqs_label', 'faqs_title', 'faqs_sub'],
            'contact'  => ['contact_label', 'contact_title', 'contact_sub', 'contact_address', 'contact_phones', 'contact_emails'],
            'location' => ['location_label', 'location_title', 'location_sub', 'location_address', 'location_directions', 'location_parking', 'location_hours', 'location_map_url'],
            'footer'   => ['brand_name', 'footer_description', 'footer_address', 'footer_phone', 'footer_email', 'footer_copyright_name', 'footer_privacy_url', 'footer_terms_url'],
        ];
    }

    /** Repeatable items, in their original page order. */
    public static function items(): array
    {
        $services = [
            ['bx-plus-medical',  'General Consultation',   'Meet with a doctor to discuss your health concerns and receive appropriate medical guidance.'],
            ['bxs-heart',        'Health Checkup',         'Routine checkups to monitor your health and detect potential concerns early.'],
            ['bxs-calendar-plus','Appointment Scheduling', 'Request an appointment online and receive a notification once the clinic confirms your schedule.'],
            ['bxs-report',       'Medical Records',        'Your visit history and medical reports, kept organized and accessible whenever you need them.'],
        ];
        $features = [
            ['bxs-calendar-week', 'Manage Appointments',         'Request, track, and reschedule appointments from your dashboard.'],
            ['bxs-file-find',     'View Medical Records',        'Access your reports and visit history at any time.'],
            ['bxs-id-card',       'Access Personal Information', 'Review and update your profile and contact details securely.'],
            ['bxs-bell-ring',     'Receive Clinic Updates',      'Receive timely notifications about the status of your appointments.'],
        ];
        $steps = [
            ['bxs-user-plus',     'Register an Account',      'Create your patient account by providing your basic information.'],
            ['bxs-user-check',    'Wait for Clinic Approval', 'Clinic staff review each new account to keep patient records secure and accurate.'],
            ['bxs-calendar-check','Book an Appointment',      'Once approved, log in to request an appointment for your preferred date and time.'],
            ['bxs-institution',   'Visit the Clinic',         'Arrive at your scheduled time and our clinical team will attend to you.'],
        ];
        $workflow = [
            ['bx-message-square-detail', 'Appointment Request'],
            ['bx-check-double',          'Approval'],
            ['bx-log-in-circle',         'Checked In'],
            ['bx-plus-medical',          'Consultation'],
            ['bx-badge-check',           'Completed'],
        ];
        $faqs = [
            ['appointments', 'How do I book an appointment?', 'Log in to your account, open the **Appointments** section, select your preferred doctor, date, and time, then submit your request. You will be notified once your appointment has been approved.'],
            ['appointments', 'Can I reschedule or cancel my appointment?', 'Yes. Open **My Appointments** in your dashboard and select the appointment you wish to cancel or reschedule. We recommend doing so at least **24 hours before** your scheduled time so that the slot may be offered to other patients.'],
            ['appointments', 'How long does it take for an appointment to be confirmed?', 'Appointments are typically reviewed and confirmed within **24 to 48 hours** during clinic operating hours. You will receive a notification whenever the status of your appointment changes.'],
            ['appointments', 'What happens if I miss my scheduled appointment?', 'If you miss your appointment, you may request a new schedule, subject to available slots. Repeated missed appointments without prior notice may affect future booking priority.'],
            ['appointments', 'Can I walk in without an appointment?', 'Walk-in patients may be accommodated depending on clinic availability and queue capacity. However, booking in advance is strongly recommended to secure your preferred schedule.'],
            ['account', 'How do I register for an account?', 'Select the **"Register"** button in the navigation bar, then provide your name, email address, and password. Verify your email through the link we send you. Your account will then be reviewed by clinic staff. Patient registration is free of charge.'],
            ['account', 'What should I do if I forget my password?', 'On the login page, select **"Forgot Password"** and enter your registered email address. We will send you a link to reset your password. If you do not see the email in your inbox, please check your spam or junk folder.'],
            ['account', 'How do I update my personal information?', 'After logging in, open **Profile Settings**, where you can update your name, contact number, and other personal details. Remember to save your changes.'],
            ['account', 'Who can access and use ClinicRMS?', 'The system supports three types of users: **Administrators**, who manage clinic operations; **Doctors**, who access their assigned patients and schedules; and **Patients**, who book appointments and view their own records. Each role is limited to the data relevant to it.'],
            ['records', 'Can I view my medical history and past consultations?', 'Yes. Your patient dashboard shows your appointment history and the medical reports issued by your doctor. Access to other patients\' records is strictly restricted to protect privacy.'],
            ['records', 'Is my medical and personal information kept confidential?', 'Yes. We use **encrypted connections (HTTPS)** and role-based access controls so that only authorized personnel can view your records. We do not share your personal or medical information with third parties.'],
            ['records', 'Can I request a copy of my medical record?', 'Yes. Requests for medical records are processed in accordance with clinic policy and after patient identity verification. Please contact the clinic staff or email **support@clinicrms.com** to start your request.'],
            ['records', 'What should I do if there is incorrect information in my record?', 'Please inform the clinic staff so that the necessary corrections can be made. You may also email **support@clinicrms.com** or call **+63 917 123 4567**.'],
            ['clinic', "What are the clinic's operating hours?", 'The clinic is open **Monday to Friday, 9:00 AM – 5:00 PM**, and **Saturday, 9:00 AM – 12:00 PM**. We are closed on Sundays and public holidays.'],
            ['clinic', 'Which doctors and specialists are available?', 'Our clinic has general practitioners and specialists on staff. For the current list, log in to your account and open the **Doctors** section, or contact the clinic at **+63 917 123 4567**.'],
            ['clinic', 'Is the system accessible on mobile phones?', 'Yes. ClinicRMS works on desktop, tablet, and smartphone with an internet connection. No application installation is required.'],
            ['clinic', 'Whom should I contact for concerns or technical assistance?', 'You may contact the clinic staff directly, email **support@clinicrms.com**, or call **+63 917 123 4567**. You may also use the Contact form on this page or ask our virtual assistant, **Viora**, for quick answers.'],
        ];

        $out = [];
        foreach ($services as $i => [$icon, $title, $body]) {
            $out[] = ['section' => 'services', 'category' => null, 'icon' => $icon, 'title' => $title, 'body' => $body, 'sort_order' => $i + 1];
        }
        foreach ($features as $i => [$icon, $title, $body]) {
            $out[] = ['section' => 'features', 'category' => null, 'icon' => $icon, 'title' => $title, 'body' => $body, 'sort_order' => $i + 1];
        }
        foreach ($steps as $i => [$icon, $title, $body]) {
            $out[] = ['section' => 'steps', 'category' => null, 'icon' => $icon, 'title' => $title, 'body' => $body, 'sort_order' => $i + 1];
        }
        foreach ($workflow as $i => [$icon, $title]) {
            $out[] = ['section' => 'workflow', 'category' => null, 'icon' => $icon, 'title' => $title, 'body' => null, 'sort_order' => $i + 1];
        }
        foreach ($faqs as $i => [$cat, $q, $a]) {
            $out[] = ['section' => 'faqs', 'category' => $cat, 'icon' => null, 'title' => $q, 'body' => $a, 'sort_order' => $i + 1];
        }

        return $out;
    }
}