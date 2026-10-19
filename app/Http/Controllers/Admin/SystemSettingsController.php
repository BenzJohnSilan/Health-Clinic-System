<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserLog;
use App\Services\ClinicSchedule;
use App\Services\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Admin > System Settings.
 *
 * Reachable only through the `admin` route group (auth + verified +
 * role:Admin) and re-checked here, so the server never relies on the
 * sidebar link being hidden. Stores clinic-wide operational settings only:
 * individual profiles stay in Account Settings, the public landing page in
 * Website Management, and secrets (SMS token, mail password) stay in .env.
 */
class SystemSettingsController extends Controller
{
    /** Human labels for the audit log. */
    private const LABELS = [
        'clinic_name' => 'Clinic name', 'clinic_address' => 'Address', 'clinic_phone' => 'Contact number',
        'clinic_email' => 'Official email',
        'slot_minutes' => 'Appointment duration (min)', 'opening_time' => 'Opening time', 'closing_time' => 'Closing time',
        'lunch_enabled' => 'Lunch break', 'lunch_start' => 'Lunch start', 'lunch_end' => 'Lunch end',
        'booking_min_notice_hours' => 'Minimum booking notice (hours)',
        'booking_max_advance_days' => 'Maximum booking window (days)',
        'notify_email_enabled' => 'Email notifications', 'notify_sms_enabled' => 'SMS notifications',
        'session_lifetime' => 'Session timeout (min)',
    ];

    public function index()
    {
        $this->authorizeAdmin();

        $settings = SystemSettings::all();

        return view('admin.system-settings', [
            'settings'        => $settings,
            'logoUrl'         => SystemSettings::logoUrl(),
            'durations'       => ClinicSchedule::DURATIONS,
            'events'          => SystemSettings::NOTIFICATION_EVENTS,
            'sessionOptions'  => SystemSettings::SESSION_LIFETIMES,
            'serverLifetime'  => SystemSettings::serverSessionLifetime(),
            'previewSlots'    => ClinicSchedule::slots(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        $current = SystemSettings::all();

        $lunchOn = $request->boolean('lunch_enabled');

        $rules = [
            // General
            'clinic_name'    => ['required', 'string', 'max:100'],
            'clinic_address' => ['nullable', 'string', 'max:255'],
            'clinic_phone'   => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'clinic_email'   => ['nullable', 'email:rfc', 'max:150'],
            'clinic_logo'    => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'remove_logo'    => ['nullable', 'boolean'],

            // Appointments
            'slot_minutes'             => ['required', 'integer', Rule::in(ClinicSchedule::DURATIONS)],
            'opening_time'             => ['required', 'date_format:H:i'],
            'closing_time'             => ['required', 'date_format:H:i'],
            'lunch_start'              => [$lunchOn ? 'required' : 'nullable', 'date_format:H:i'],
            'lunch_end'                => [$lunchOn ? 'required' : 'nullable', 'date_format:H:i'],
            'booking_min_notice_hours' => ['required', 'integer', 'between:0,168'],
            'booking_max_advance_days' => ['required', 'integer', 'between:0,365'],

            // Security ('' = keep the server default)
            'session_lifetime' => ['nullable', Rule::in(array_map('strval', SystemSettings::SESSION_LIFETIMES))],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'clinic_phone.regex'       => 'The contact number may only contain digits, spaces, + ( ) and -.',
            'clinic_logo.image'        => 'The logo must be an image file.',
            'clinic_logo.mimes'        => 'The logo must be a JPG, PNG or WEBP image.',
            'clinic_logo.max'          => 'The logo may not be larger than 2 MB.',
            'clinic_logo.dimensions'   => 'The logo may not be larger than 3000 x 3000 pixels.',
            'clinic_logo.uploaded'     => 'The logo could not be uploaded. Please try a smaller image.',
            'session_lifetime.in'      => 'Please choose one of the listed session timeouts.',
        ]);

        $validator->after(function ($v) use ($request, $lunchOn) {
            if ($v->errors()->hasAny(['slot_minutes', 'opening_time', 'closing_time', 'lunch_start', 'lunch_end'])) {
                return;
            }

            $open  = $request->input('opening_time');
            $close = $request->input('closing_time');
            $slot  = (int) $request->input('slot_minutes');

            if ($close <= $open) {
                $v->errors()->add('closing_time', 'Closing time must be later than opening time.');
                return;
            }

            if (ClinicSchedule::minutes($close) - ClinicSchedule::minutes($open) < $slot) {
                $v->errors()->add('closing_time', 'Operating hours must fit at least one appointment.');
                return;
            }

            if (!$lunchOn) {
                return;
            }

            $ls = $request->input('lunch_start');
            $le = $request->input('lunch_end');

            if ($le <= $ls) {
                $v->errors()->add('lunch_end', 'Lunch break must end after it starts.');
            } elseif ($ls < $open || $le > $close) {
                $v->errors()->add('lunch_start', 'The lunch break must be inside the operating hours.');
            } elseif (!ClinicSchedule::buildSlots($slot, $open, $close, true, $ls, $le)
                || count(array_filter(ClinicSchedule::buildSlots($slot, $open, $close, true, $ls, $le), fn ($r) => !$r['lunch'])) === 0) {
                $v->errors()->add('lunch_start', 'These hours leave no bookable appointment slots.');
            }
        });

        $data = $validator->validate();

        // ---------------------------------------------------------------
        // Build the values to store.
        // ---------------------------------------------------------------
        $new = [
            'clinic_name'    => trim($data['clinic_name']),
            'clinic_address' => trim((string) ($data['clinic_address'] ?? '')),
            'clinic_phone'   => trim((string) ($data['clinic_phone'] ?? '')),
            'clinic_email'   => trim((string) ($data['clinic_email'] ?? '')),

            'slot_minutes'             => (string) (int) $data['slot_minutes'],
            'opening_time'             => $data['opening_time'],
            'closing_time'             => $data['closing_time'],
            'lunch_enabled'            => $lunchOn ? '1' : '0',
            // Keep the previous lunch times when the break is switched off.
            'lunch_start'              => $lunchOn ? $data['lunch_start'] : $current['lunch_start'],
            'lunch_end'                => $lunchOn ? $data['lunch_end'] : $current['lunch_end'],
            'booking_min_notice_hours' => (string) (int) $data['booking_min_notice_hours'],
            'booking_max_advance_days' => (string) (int) $data['booking_max_advance_days'],

            'session_lifetime' => (string) ($data['session_lifetime'] ?? ''),
        ];

        foreach (['notify_email_enabled', 'notify_sms_enabled'] as $key) {
            $new[$key] = $request->boolean($key) ? '1' : '0';
        }
        foreach (SystemSettings::NOTIFICATION_EVENTS as $event => $meta) {
            foreach ($meta['channels'] as $channel) {
                $key = "notify_{$channel}_{$event}";
                $new[$key] = $request->boolean($key) ? '1' : '0';
            }
        }

        // ---------------------------------------------------------------
        // Logo: keep the current one unless replaced or removed.
        // ---------------------------------------------------------------
        $oldLogo     = (string) $current['clinic_logo'];
        $newLogo     = $oldLogo;
        $logoChanged = null;

        if ($request->hasFile('clinic_logo')) {
            // Random hashed name + extension guessed from the real MIME type;
            // the client-supplied file name is never used.
            $newLogo     = $request->file('clinic_logo')->store(SystemSettings::LOGO_DIR, 'public');
            $logoChanged = $oldLogo !== '' ? 'replaced' : 'uploaded';
        } elseif ($request->boolean('remove_logo') && $oldLogo !== '') {
            $newLogo     = '';
            $logoChanged = 'removed';
        }
        $new['clinic_logo'] = $newLogo;

        // ---------------------------------------------------------------
        // Persist, then clean up the old file only after the save worked.
        // ---------------------------------------------------------------
        try {
            SystemSettings::set($new);
        } catch (\Throwable $e) {
            if ($logoChanged && $newLogo !== $oldLogo) {
                SystemSettings::deleteLogo($newLogo);
            }
            report($e);

            return back()->withInput()->with('error', 'Settings could not be saved. Please try again.');
        }

        if ($logoChanged && $oldLogo !== $newLogo) {
            SystemSettings::deleteLogo($oldLogo);
        }

        // ---------------------------------------------------------------
        // Audit log (no secrets exist in these settings).
        // ---------------------------------------------------------------
        $changes = [];
        foreach (self::LABELS as $key => $label) {
            $before = (string) ($current[$key] ?? '');
            $after  = (string) ($new[$key] ?? '');

            if ($before === $after) {
                continue;
            }

            if (in_array($key, ['lunch_enabled'], true) || str_starts_with($key, 'notify_')) {
                $before = $before === '1' ? 'on' : 'off';
                $after  = $after === '1' ? 'on' : 'off';
            } elseif ($key === 'session_lifetime') {
                $before = $before === '' ? 'server default' : $before;
                $after  = $after === '' ? 'server default' : $after;
            }

            $changes[] = "{$label}: " . ($before === '' ? '(empty)' : $before) . ' → ' . ($after === '' ? '(empty)' : $after);
        }

        foreach (SystemSettings::NOTIFICATION_EVENTS as $event => $meta) {
            foreach ($meta['channels'] as $channel) {
                $key = "notify_{$channel}_{$event}";
                if (($current[$key] ?? '1') !== $new[$key]) {
                    $changes[] = strtoupper($channel) . ' ' . strtolower($meta['label']) . ': '
                        . ($new[$key] === '1' ? 'on' : 'off');
                }
            }
        }

        if ($logoChanged) {
            $changes[] = "Clinic logo {$logoChanged}";
        }

        if ($changes) {
            UserLog::create([
                'user_id' => auth()->id(),
                'action'  => 'Updated System Settings',
                'module'  => 'System Settings',
                'details' => mb_substr(implode('; ', $changes), 0, 4000),
            ]);
        }

        return redirect()->route('admin.system-settings.index')
            ->with('success', $changes ? 'System settings saved successfully.' : 'No changes were made.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->role === 'Admin', 403, 'Unauthorized access');
    }
}
