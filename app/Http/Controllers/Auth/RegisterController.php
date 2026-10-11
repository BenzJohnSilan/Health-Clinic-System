<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\OtpVerificationNotification;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegisterController extends Controller
{
    /** Valid ID type options offered on the registration form. */
    private const ID_TYPES = ['PhilHealth', "Driver's License", 'Passport', 'SSS', 'UMID', "Voter's ID", 'Others'];

    /** Private-disk folders. */
    private const STAGING_DIR = 'registration_tmp'; // validated file kept between failed attempts
    private const FINAL_DIR   = 'valid_ids';        // permanent home of a registered patient's ID

    public function __construct(private EmailOtpService $otp)
    {
    }

    public function show()
    {
        // Drops a stale/invalid session reference so the view only shows a
        // preview when a real, previously validated file is waiting.
        $this->stagedPath();

        return view('auth.register');
    }

    /**
     * Preview of the Valid ID the visitor already uploaded in THIS session
     * (shown when the form is re-displayed after a validation error).
     *
     * No id/path comes from the request: the file is looked up only from the
     * visitor's own session, so nobody can use it to read another file.
     */
    public function stagedValidIdPreview()
    {
        $path = $this->stagedPath();

        abort_if($path === null, 404);

        return Storage::disk('private')->response($path, null, [
            'Cache-Control'           => 'private, no-store, max-age=0',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    public function store(Request $request)
    {
        $staged = $this->stagedPath();

        // ── 1. Validate EVERYTHING first (fields + the uploaded file) ────────────────
        $validator = Validator::make($request->all(), $this->rules($staged !== null), $this->messages());

        // Per-requirement password checks, each with its own message. Only run
        // once the basic password rules passed so the visitor isn't flooded.
        $validator->after(function ($v) use ($request) {
            if ($v->errors()->has('password')) {
                return;
            }

            $pw = (string) $request->input('password', '');

            if (!preg_match('/[A-Z]/', $pw)) {
                $v->errors()->add('password', 'Password must contain at least one uppercase letter (A-Z).');
            }
            if (!preg_match('/[a-z]/', $pw)) {
                $v->errors()->add('password', 'Password must contain at least one lowercase letter (a-z).');
            }
            if (!preg_match('/[0-9]/', $pw)) {
                $v->errors()->add('password', 'Password must contain at least one number (0-9).');
            }
            if (!preg_match('/[@$!%*#?&]/', $pw)) {
                $v->errors()->add('password', 'Password must contain at least one special character (@$!%*#?&).');
            }
        });

        if ($validator->fails()) {
            // Browsers cannot re-fill <input type="file">. If the ID itself is
            // fine and only other fields failed, keep it (privately, under a
            // random name) so the patient does not have to pick it again.
            $this->stageUploadIfValid($request);

            throw new ValidationException($validator);
        }

        // ── 2. Store the ID privately, then create the account atomically ────────────
        $storedPath = null;

        try {
            $storedPath = $this->storeValidId($request, $staged);

            [$user, $otp] = DB::transaction(function () use ($request, $storedPath) {
                // Explicit server-side values only. Nothing security-relevant
                // (role / status / approval_status / permissions) is ever read
                // from the request.
                $user = User::create([
                    'first_name'     => $request->input('first_name'),
                    'middle_name'    => $request->input('middle_name'),
                    'last_name'      => $request->input('last_name'),
                    'suffix'         => $request->input('suffix'),
                    'birthdate'      => $request->input('birthdate'),
                    'gender'         => $request->input('gender'),
                    'civil_status'   => $request->input('civil_status'),
                    'address'        => $request->input('address'),
                    'contact_number' => $request->input('contact_number'),

                    'id_type'        => $request->input('id_type'),
                    'valid_id'       => $storedPath,

                    // Profile Picture, Medical Information and Emergency
                    // Contact are completed later by the patient via the
                    // Patient Profile page (not collected at registration).

                    'username'       => $request->input('username'),
                    'email'          => $request->input('email'),
                    'password'       => Hash::make($request->input('password')),

                    'role'            => 'Patient',
                    'status'          => 'Active',
                    'approval_status' => 'Pending',
                ]);

                // Grants this brand-new Patient only the default permissions
                // for the Patient role (Billing & Payments read-only).
                Permission::assignDefaultsTo($user);

                return [$user, $this->otp->issue($user)];
            });
        } catch (Throwable $e) {
            // Never leave an orphaned Valid ID behind.
            if ($storedPath !== null) {
                Storage::disk('private')->delete($storedPath);
            }

            report($e);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['registration' => 'We could not complete your registration right now. Please try again.']);
        }

        // The ID now belongs to the account — the staged copy is no longer needed.
        $this->discardStaged();

        // ── 3. Email the OTP (the account exists even if the mail server hiccups;
        //       the patient can use "Resend" on the verification page) ──────────────
        $mailFailed = false;

        try {
            $user->notify(new OtpVerificationNotification($otp));
        } catch (Throwable $e) {
            report($e);
            $mailFailed = true;
        }

        Log::info('registration.succeeded', ['user_id' => $user->id, 'ip' => $request->ip()]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('verification.notice')
            ->with($mailFailed ? 'error' : 'success', $mailFailed
                ? 'Account created, but we could not send the verification code. Please press "Resend" below.'
                : 'Account created successfully! Please verify your email to continue.');
    }

    // =====================================================================
    //  Validation
    // =====================================================================

    private function rules(bool $hasStagedId): array
    {
        return [
            // PERSONAL INFO (lengths match the database columns)
            'first_name'   => ['required', 'string', 'max:50', "regex:/^[\\p{L}\\p{M}\\s.'\\-]+$/u"],
            'middle_name'  => ['nullable', 'string', 'max:50', "regex:/^[\\p{L}\\p{M}\\s.'\\-]+$/u"],
            'last_name'    => ['required', 'string', 'max:50', "regex:/^[\\p{L}\\p{M}\\s.'\\-]+$/u"],
            'suffix'       => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9.\\s]+$/'],
            'birthdate'    => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'gender'       => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'civil_status' => ['required', Rule::in(['Single', 'Married', 'Widowed', 'Separated'])],
            'address'      => ['required', 'string', 'max:100'],

            'contact_number' => ['required', 'regex:/^09[0-9]{9}$/', 'unique:users,contact_number'],

            // VERIFICATION
            'id_type'  => ['required', 'string', Rule::in(self::ID_TYPES)],
            'valid_id' => array_merge([$hasStagedId ? 'nullable' : 'required'], $this->fileRules()),

            // LOGIN INFO
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._\\-]+$/', 'unique:users,username'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],

            // Basic rules first (each with its own message). `bail` stops at the
            // first failure so the "has this password leaked?" lookup only runs
            // for passwords that already look acceptable.
            'password' => [
                'bail', 'required', 'confirmed', 'min:8',
                'different:username', 'different:email',
                Password::min(8)->uncompromised(),
            ],

            // Privacy Notice must be ticked
            'consent' => ['accepted'],
        ];
    }

    /** Valid ID rules: real image upload, content-sniffed type, max 2 MB. */
    private function fileRules(): array
    {
        return ['file', 'image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:2048'];
    }

    private function messages(): array
    {
        return [
            'first_name.regex'  => 'First name may only contain letters, spaces, apostrophes, hyphens and periods.',
            'middle_name.regex' => 'Middle name may only contain letters, spaces, apostrophes, hyphens and periods.',
            'last_name.regex'   => 'Last name may only contain letters, spaces, apostrophes, hyphens and periods.',
            'suffix.regex'      => 'Suffix may only contain letters, numbers, spaces and periods.',
            'contact_number.regex'  => 'Contact number must start with 09 and be exactly 11 digits.',
            'contact_number.unique' => 'This contact number is already registered. Please use a different number.',
            'email.unique'          => 'This email is already registered. Please log in or use a different email.',
            'username.unique'       => 'This username is already taken. Please choose another one.',
            'username.regex'        => 'Username may only contain letters, numbers, dots, dashes and underscores.',
            'valid_id.required'     => 'Please upload a valid ID.',
            'valid_id.uploaded'     => 'The valid ID could not be uploaded. Please use a JPG or PNG file under 2 MB.',
            'valid_id.image'        => 'The valid ID must be a JPG or PNG image.',
            'valid_id.mimes'        => 'The valid ID must be a JPG or PNG image.',
            'valid_id.mimetypes'    => 'The valid ID must be a JPG or PNG image.',
            'valid_id.max'          => 'The valid ID must not be larger than 2 MB.',
            'password.required'     => 'Password is required.',
            'password.confirmed'    => 'Password confirmation does not match.',
            'password.min'          => 'Password must be at least 8 characters long.',
            'password.different'    => 'Your password must not be the same as your username or email.',
            'consent.accepted'      => 'You must agree to the Privacy Notice to create an account.',
        ];
    }

    // =====================================================================
    //  Valid ID storage (private disk only)
    // =====================================================================

    /**
     * Put the ID in its permanent private location under a random,
     * server-generated name. The visitor's original filename is never used and
     * the extension comes from the file's real content, not from its name.
     *
     * @return string path relative to the private disk, e.g. valid_ids/<random>.jpg
     */
    private function storeValidId(Request $request, ?string $staged): string
    {
        $file = $request->file('valid_id');

        if ($file instanceof UploadedFile) {
            return $file->store(self::FINAL_DIR, 'private');
        }

        // No new upload: use the file validated on an earlier attempt.
        $extension = pathinfo((string) $staged, PATHINFO_EXTENSION);
        $path      = self::FINAL_DIR . '/' . Str::random(40) . '.' . $extension;

        Storage::disk('private')->copy($staged, $path);

        return $path;
    }

    /** Keep a validated upload privately for the next attempt of this session. */
    private function stageUploadIfValid(Request $request): void
    {
        $file = $request->file('valid_id');

        if (!$file instanceof UploadedFile) {
            return;
        }

        $passes = Validator::make(['valid_id' => $file], ['valid_id' => $this->fileRules()])->passes();

        if (!$passes) {
            return;
        }

        $this->discardStaged();

        session(['temp_valid_id' => $file->store(self::STAGING_DIR, 'private')]);
    }

    /**
     * The staged file for this session, or null. A session value that does not
     * look exactly like something we generated, or whose file is gone, is dropped.
     */
    private function stagedPath(): ?string
    {
        $path = session('temp_valid_id');

        if (is_string($path)
            && preg_match('#^' . self::STAGING_DIR . '/[A-Za-z0-9]{40}\.(jpe?g|png)$#', $path) === 1
            && Storage::disk('private')->exists($path)) {
            return $path;
        }

        session()->forget('temp_valid_id');

        return null;
    }

    private function discardStaged(): void
    {
        $path = session('temp_valid_id');

        if (is_string($path)
            && preg_match('#^' . self::STAGING_DIR . '/[A-Za-z0-9]{40}\.(jpe?g|png)$#', $path) === 1) {
            Storage::disk('private')->delete($path);
        }

        session()->forget('temp_valid_id');
    }
}