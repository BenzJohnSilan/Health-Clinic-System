<?php

use App\Support\LandingDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Professional wording refresh for the public landing page.
 *
 * Databases created earlier hold the previous default text. This replaces a
 * row ONLY while it still matches that previous default exactly, so anything
 * the Admin has edited in Website Management is never overwritten.
 */
return new class extends Migration
{
    private function oldSettings(): array
    {
        return [
        'hero_badge' => 'Secure Patient Portal',
        'hero_title_line1' => 'Your Health,',
        'hero_title_highlight' => 'Our Priority',
        'hero_description' => 'Manage your appointments and access your medical records anytime through our clinic\'s secure, easy-to-use online patient portal.',
        'hero_primary_label' => 'Get Started',
        'hero_secondary_label' => 'Learn More',
        'about_label' => 'Who We Are',
        'about_title' => 'About Our',
        'about_title_highlight' => 'Clinic',
        'about_text_1' => 'Our clinic is committed to providing accessible and organized healthcare services for the community. Through our online patient portal, patients can manage appointments and access their personal health records in one convenient platform.',
        'about_text_2' => 'Whether you\'re a patient booking your first appointment or a member of our clinical team, our system provides a smooth and secure experience tailored to your role — with access strictly limited to what\'s relevant to you.',
        'services_label' => 'What We Offer',
        'services_title' => 'Our Services',
        'services_sub' => 'Straightforward, well-organized care — supported by our online patient portal.',
        'features_label' => 'Your Account',
        'features_title' => 'What You Can Do In The Patient Portal',
        'features_sub' => 'Once your account is approved, everything you need is a login away.',
        'steps_label' => 'Getting Started',
        'steps_title' => 'How It Works',
        'steps_sub' => 'Getting access to your patient account takes just a few simple steps.',
        'workflow_label' => 'Behind The Scenes',
        'workflow_title' => 'How Your Appointment Is Handled',
        'workflow_sub' => 'A simple, transparent workflow from request to completed visit.',
        'faqs_label' => 'Got Questions?',
        'faqs_title' => 'Frequently Asked Questions',
        'faqs_sub' => 'Find answers to the most common questions about our clinic system. Can\'t find what you\'re looking for? Chat with Viora!',
        'contact_label' => 'Get In Touch',
        'contact_title' => 'Contact Us',
        'contact_sub' => 'Have questions or need support? Reach out to us and we\'ll get back to you as soon as possible.',
        'contact_address' => 'Barangay Dayap, Calauan,
Laguna, Philippines',
        'contact_phones' => '+63 917 123 4567
+63 2 8123 4567',
        'contact_emails' => 'support@clinicrms.com
admin@clinicrms.com',
        'location_label' => 'Find Us',
        'location_title' => 'Clinic Information',
        'location_sub' => 'Visit us at our clinic. We\'re conveniently located and easy to find.',
        'location_address' => 'Barangay Dayap, Calauan,
Laguna 4012, Philippines',
        'location_directions' => 'From Santa Cruz, take a jeepney or bus bound for Calauan. Alight at Calauan town proper and take a tricycle to Barangay Dayap.',
        'location_parking' => 'Free parking available inside the clinic compound for patients and visitors.',
        'location_hours' => 'Mon–Fri: 9:00 AM – 5:00 PM
Saturday: 9:00 AM – 12:00 PM
Sunday: Closed',
        'location_map_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.1!2d121.3167!3d14.1500!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd5b2e2e2e2e2f%3A0x0!2sBrgy.+Dayap%2C+Calauan%2C+Laguna!5e0!3m2!1sen!2sph!4v1',
        'brand_name' => 'ClinicRMS',
        'footer_description' => 'Our clinic\'s official online patient portal for managing appointments and medical records, securely and in one place.',
        'footer_address' => 'Barangay Dayap, Calauan, Laguna 4012, Philippines',
        'footer_phone' => '+63 917 123 4567',
        'footer_email' => 'support@clinicrms.com',
        'footer_copyright_name' => 'Clinic Record System',
        'footer_privacy_url' => '#',
        'footer_terms_url' => '#',
    ];
    }

    /** "section|sort_order" => [old title, old body] */
    private function oldItems(): array
    {
        return [
        'services|1' => ['General Consultation', 'Sit down with a doctor to discuss your health concerns and get the guidance you need.'],
        'services|2' => ['Health Checkup', 'Routine checkups to help keep track of your health and catch concerns early.'],
        'services|3' => ['Appointment Scheduling', 'Request a visit online and get notified as soon as the clinic confirms your slot.'],
        'services|4' => ['Medical Records', 'Your visit history and reports, kept organized and accessible whenever you need them.'],
        'features|1' => ['Manage Appointments', 'Request, track, and reschedule your appointments from your dashboard.'],
        'features|2' => ['View Medical Records', 'Access your own reports and visit history whenever you need them.'],
        'features|3' => ['Access Personal Information', 'Review and update your profile and contact details securely.'],
        'features|4' => ['Receive Clinic Updates', 'Get organized, real-time notifications about your appointment status.'],
        'steps|1' => ['Register an Account', 'Create your patient account with your basic information in just a few minutes.'],
        'steps|2' => ['Wait for Clinic Approval', 'Our clinic staff reviews new patient accounts to keep records secure and accurate.'],
        'steps|3' => ['Book an Appointment', 'Once approved, log in anytime to request an appointment for your preferred date.'],
        'steps|4' => ['Visit the Clinic', 'Arrive for your scheduled visit and let our clinic team take care of the rest.'],
        'workflow|1' => ['Appointment Request', null],
        'workflow|2' => ['Approval', null],
        'workflow|3' => ['Checked In', null],
        'workflow|4' => ['Consultation', null],
        'workflow|5' => ['Completed', null],
        'faqs|1' => ['How do I book an appointment?', 'Log in to your account, go to the **Appointments** section, select your preferred doctor, date, and time, then submit your booking request. You will receive a notification once your appointment has been approved.'],
        'faqs|2' => ['Can I reschedule or cancel my appointment?', 'Yes. Go to **My Appointments** in your dashboard and select the appointment you wish to cancel or reschedule. We recommend doing so at least **24 hours before** your scheduled time to allow other patients to book that slot.'],
        'faqs|3' => ['How long does it take for an appointment to be confirmed?', 'Appointments are typically reviewed and confirmed within **24 to 48 hours** during clinic operating hours. You will receive a system notification whenever the status of your booking changes.'],
        'faqs|4' => ['What happens if I miss my scheduled appointment?', 'If you miss your appointment, you may request a new schedule depending on available clinic slots. Repeated missed appointments without prior notice may affect future booking priority.'],
        'faqs|5' => ['Can I walk in without an appointment?', 'Walk-in patients may still be accommodated depending on clinic availability and queue capacity. However, booking in advance is strongly recommended to secure your preferred time slot.'],
        'faqs|6' => ['How do I register for an account?', 'Click the **"Register"** button in the navigation bar. Fill in your name, email address, and password. Verify your email through the link we send you, and you\'re ready to use the system. Registration as a patient is completely free.'],
        'faqs|7' => ['I forgot my password. What should I do?', 'On the login page, click **"Forgot Password"** and enter your registered email address. We will send you a link to reset your password. Check your spam or junk folder if you don\'t see the email in your inbox.'],
        'faqs|8' => ['How do I update my personal information?', 'Go to **Profile Settings** after logging in. From there, you can edit your name, contact number, and other personal details. Make sure to save your changes when done.'],
        'faqs|9' => ['Who can access and use ClinicRMS?', 'The system supports three types of users: **Admins** who manage clinic operations, **Doctors** who access their assigned patients and schedules, and **Patients** who can book appointments and view their own records. Each role has controlled access to relevant data only.'],
        'faqs|10' => ['Can I view my medical history and past consultations?', 'Yes. In your patient dashboard, you can view your appointment history and medical reports issued by your doctor. Access to other patients\' records is strictly restricted to protect privacy.'],
        'faqs|11' => ['Will my medical records and personal information remain confidential?', 'Yes. We use **encrypted connections (HTTPS)** and role-based access controls to ensure only authorized personnel can view your records. We never share your personal or medical data with third parties.'],
        'faqs|12' => ['Can I request a copy of my medical record?', 'Yes. Requests for medical records may be processed following clinic policies and proper patient verification procedures. Please contact the clinic staff or email us at **support@clinicrms.com** to initiate your request.'],
        'faqs|13' => ['What should I do if there is incorrect information in my record?', 'Inform the clinic staff immediately so the necessary corrections can be made. You may also reach us at **support@clinicrms.com** or call **+63 917 123 4567** for assistance.'],
        'faqs|14' => ['What are the clinic\'s operating hours?', 'The clinic is open **Monday through Friday, 9:00 AM – 5:00 PM** and **Saturday, 9:00 AM – 12:00 PM**. We are closed on Sundays and public holidays.'],
        'faqs|15' => ['What doctors and specialists are available?', 'We have general practitioners and various specialists on staff. For the most up-to-date list, log in to your account and visit the **Doctors** section, or contact the clinic directly at **+63 917 123 4567**.'],
        'faqs|16' => ['Is the system accessible on mobile phones?', 'Yes. ClinicRMS is fully responsive and works on all devices — desktop, tablet, or smartphone — with an internet connection. No app installation is required.'],
        'faqs|17' => ['Who should I contact for concerns or technical assistance?', 'You may contact the clinic staff directly, email us at **support@clinicrms.com**, or call **+63 917 123 4567**. You can also use the Contact form on this page or chat with our AI assistant **Viora** for quick answers.'],
    ];
    }

    public function up(): void
    {
        $new = LandingDefaults::settings();

        if (Schema::hasTable('website_settings')) {
            foreach ($this->oldSettings() as $key => $old) {
                if (!isset($new[$key]) || $new[$key] === $old) {
                    continue;
                }
                DB::table('website_settings')
                    ->where('key', $key)->where('value', $old)
                    ->update(['value' => $new[$key], 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('landing_items')) {
            $newItems = [];
            foreach (LandingDefaults::items() as $row) {
                $newItems[$row['section'] . '|' . $row['sort_order']] = $row;
            }
            foreach ($this->oldItems() as $key => [$oldTitle, $oldBody]) {
                [$section, $order] = explode('|', $key);
                if (!isset($newItems[$key])) {
                    continue;
                }
                $q = DB::table('landing_items')
                    ->where('section', $section)->where('sort_order', (int) $order)
                    ->where('title', $oldTitle);
                $oldBody === null ? $q->whereNull('body') : $q->where('body', $oldBody);
                $q->update([
                    'title' => $newItems[$key]['title'],
                    'body' => $newItems[$key]['body'],
                    'updated_at' => now(),
                ]);
            }
        }

        \Illuminate\Support\Facades\Cache::forget('landing.content.v1');
    }

    public function down(): void
    {
        // Wording refresh only; nothing to undo.
    }
};