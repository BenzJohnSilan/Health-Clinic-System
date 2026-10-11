@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-laboratory-show.css') }}">
@endsection

@section('content')

@php
    $statusClassMap = [
        'Awaiting Result'     => 'awaiting',
        'Result Received'     => 'received',
        'Correction Required' => 'correction',
        'Reviewed'            => 'reviewed',
    ];
    $statusIconMap = [
        'Awaiting Result'     => 'bx-time-five',
        'Result Received'     => 'bx-download',
        'Correction Required' => 'bx-error-circle',
        'Reviewed'            => 'bx-check-circle',
    ];
    $statusClass = $statusClassMap[$laboratoryRequest->status] ?? 'awaiting';
    $statusIcon  = $statusIconMap[$laboratoryRequest->status] ?? 'bx-time-five';

    // Computed here (not inside the review-card branch below) so
    // it's always available to the Request Correction modal at
    // the bottom of this page, regardless of the current status.
    $pendingByTest = $laboratoryRequest->pendingCorrectionsByTest();

    $isWalkIn   = $laboratoryRequest->walkin_patient_id !== null;
    $patientObj = $laboratoryRequest->resolvedPatient();
@endphp

<div class="container lr-page">

    {{-- BACK LINK --}}
    <a href="{{ route('doctor.laboratory-requests.index') }}" class="lr-back">
        <i class='bx bx-arrow-back'></i> Back to Laboratory Results
    </a>

    {{-- ================= HERO ================= --}}
    <div class="lr-hero">
        <div class="lr-hero__left">
            <div class="lr-hero__icon"><i class='bx bxs-flask'></i></div>
            <div>
                <h1 class="lr-hero__title">Laboratory Result Review</h1>
                <p class="lr-hero__sub">{{ $laboratoryRequest->patientName() }}</p>
                <div class="lr-hero__chips">
                    <span class="lr-chip"><i class='bx bx-hash'></i> {{ $laboratoryRequest->reference_no }}</span>
                    @if($laboratoryRequest->requested_at)
                        <span class="lr-chip"><i class='bx bx-calendar'></i> {{ $laboratoryRequest->requested_at->format('F d, Y') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <span class="lr-hero__status lr-hero__status--{{ $statusClass }}">
            <i class='bx {{ $statusIcon }}'></i> {{ $laboratoryRequest->status }}
        </span>
    </div>

    @if(session('success'))
        <div class="lr-alert lr-alert--success"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="lr-alert lr-alert--error"><i class='bx bx-error-circle'></i> {{ session('error') }}</div>
    @endif

    {{-- ================= TOP SECTION: PATIENT INFO + LAB REQUEST ================= --}}
    <div class="lr-grid lr-grid--top">

        {{-- PATIENT INFORMATION --}}
        <div class="lr-card">
            <div class="lr-card__head">
                <div class="lr-card__icon"><i class='bx bx-user-circle'></i></div>
                <h2>Patient Information</h2>
            </div>

            <dl class="lr-fields">
                <div><dt>Patient Type</dt><dd>{{ $isWalkIn ? 'Walk-in Patient' : 'Registered Patient' }}</dd></div>
                <div><dt>Full Name</dt><dd>{{ $laboratoryRequest->patientName() }}</dd></div>
                <div><dt>Age</dt><dd>{{ $patientObj?->age ?? '—' }}</dd></div>
                <div><dt>Gender</dt><dd>{{ $patientObj?->gender ?? '—' }}</dd></div>
            </dl>
        </div>

        {{-- LABORATORY REQUEST --}}
        <div class="lr-card">
            <div class="lr-card__head">
                <div class="lr-card__icon"><i class='bx bx-clipboard'></i></div>
                <h2>Laboratory Request</h2>
            </div>

            <dl class="lr-fields">
                <div><dt>Reference No.</dt><dd>{{ $laboratoryRequest->reference_no }}</dd></div>
                <div><dt>Request Date</dt><dd>{{ $laboratoryRequest->requested_at?->format('F d, Y g:i A') ?? '—' }}</dd></div>
                <div><dt>Requesting Doctor</dt><dd>Dr. {{ $laboratoryRequest->doctor->first_name }} {{ $laboratoryRequest->doctor->last_name }}</dd></div>
                @if($laboratoryRequest->diagnosis)
                    <div><dt>Diagnosis</dt><dd>{{ $laboratoryRequest->diagnosis }}</dd></div>
                @endif
                <div class="lr-fields__wide">
                    <dt>Requested Tests</dt>
                    <dd>
                        @forelse($laboratoryRequest->tests as $test)
                            <span class="lr-test-chip">{{ $test->test_name }}</span>
                        @empty
                            {{ $laboratoryRequest->testsDisplay() }}
                        @endforelse
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- ================= LABORATORY RESULTS ================= --}}
    <div class="lr-card lr-card--spaced">
        <div class="lr-card__head">
            <div class="lr-card__icon"><i class='bx bx-file-find'></i></div>
            <h2>Laboratory Results</h2>
        </div>

        @if($laboratoryRequest->results->isEmpty())
            <div class="lr-empty">
                <i class='bx bx-upload'></i>
                <p>No result files have been uploaded yet.</p>
            </div>
        @else
            @php
                // Group uploaded files by which test they were tagged with,
                // so multiple tests each show their own file(s) grouped
                // together (Section 8 of the workflow spec).
                $groupedResults = $laboratoryRequest->results->groupBy(function ($result) {
                    return $result->test_name ?: 'General';
                });
            @endphp

            @foreach($groupedResults as $groupName => $files)
                <div class="lr-group">
                    <div class="lr-group__title">{{ $groupName }}</div>

                    @foreach($files as $file)
                        <div class="lr-file">
                            <div class="lr-file__info">
                                <div class="lr-file__icon">
                                    <i class='bx {{ $file->file_type === "pdf" ? "bxs-file-pdf" : "bxs-file-image" }}'></i>
                                </div>
                                <div class="lr-file__text">
                                    <div class="lr-file__name">{{ $file->original_filename }}</div>
                                    <div class="lr-file__meta">
                                        {{ $file->readable_size }} · Uploaded by {{ $file->uploader->first_name ?? 'Unknown' }} {{ $file->uploader->last_name ?? '' }}
                                        · {{ $file->uploaded_at?->format('M d, Y g:i A') }}
                                        @if($file->notes)
                                            · "{{ $file->notes }}"
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <a href="{{ $file->url }}" target="_blank" class="lr-btn lr-btn--outline lr-btn--sm">
                                <i class='bx bx-show'></i> View Result
                            </a>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>

    {{-- ================= LOWER SECTION: DOCTOR REVIEW + FOLLOW-UP ================= --}}
    <div class="lr-grid lr-grid--lower{{ $laboratoryRequest->follow_up_recommended ? '' : ' lr-grid--solo' }}">

        {{-- DOCTOR REVIEW --}}
        <div class="lr-card">
            <div class="lr-card__head">
                <div class="lr-card__icon lr-card__icon--green"><i class='bx bx-check-shield'></i></div>
                <h2>Doctor Review</h2>
            </div>

            @if($laboratoryRequest->isReviewed())
                <dl class="lr-fields">
                    <div>
                        <dt>Reviewed By</dt>
                        <dd>Dr. {{ $laboratoryRequest->reviewedBy->first_name ?? '' }} {{ $laboratoryRequest->reviewedBy->last_name ?? '' }}</dd>
                    </div>
                    <div>
                        <dt>Reviewed On</dt>
                        <dd>{{ $laboratoryRequest->reviewed_at?->format('F d, Y g:i A') ?? '—' }}</dd>
                    </div>
                    @if($laboratoryRequest->review_notes)
                        <div class="lr-fields__wide">
                            <dt>Interpretation / Remarks</dt>
                            <dd class="lr-pre">{{ $laboratoryRequest->review_notes }}</dd>
                        </div>
                    @endif
                    <div class="lr-fields__wide">
                        <dt>Follow-up Consultation</dt>
                        <dd>
                            @if($laboratoryRequest->follow_up_recommended)
                                <span class="lr-pill lr-pill--blue">Recommended</span>
                                @if($laboratoryRequest->follow_up_instructions)
                                    <div class="lr-followup-text lr-pre">{{ $laboratoryRequest->follow_up_instructions }}</div>
                                @endif
                            @else
                                Not required
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="lr-note lr-note--success">
                    <i class='bx bx-check-circle'></i> This laboratory result has already been reviewed.
                </div>

            @elseif($laboratoryRequest->isCorrectionRequired() || $laboratoryRequest->isResultReceived())
                @php
                    // Each requested test carries its OWN correction state —
                    // CBC can be "Correction Needed" while FBS is fine at the
                    // same time (Section 8 of the multi-test correction spec).
                    $openCorrections = $pendingByTest->filter();
                    // Only tests that aren't ALREADY flagged can be flagged
                    // again — prevents a confusing duplicate pending correction.
                    $correctableTests = $pendingByTest->filter(fn ($c) => $c === null)->keys();
                @endphp

                @if($openCorrections->isNotEmpty())
                    <div class="lr-correction-panel">
                        <div class="lr-correction-panel__title">
                            <i class='bx bx-error-circle'></i>
                            {{ $openCorrections->count() > 1 ? 'Corrections Requested' : 'Correction Requested' }}
                        </div>

                        @foreach($openCorrections as $testName => $pending)
                            <div class="lr-correction-panel__row">
                                <span class="lr-correction-panel__test">{{ $testName }}</span>
                                <span class="lr-correction-panel__reason">"{{ $pending->reason }}"</span>
                            </div>
                        @endforeach

                        <p class="lr-correction-panel__note">
                            Waiting for the corrected result(s) above. The original uploads have been kept — nothing was deleted.
                        </p>
                    </div>
                @endif

                <form action="{{ route('doctor.laboratory-requests.review', $laboratoryRequest->id) }}" method="POST">
                    @csrf

                    <div class="lr-form-group">
                        <label class="lr-label" for="review_notes">Interpretation / Remarks</label>
                        <textarea name="review_notes" id="review_notes" class="lr-textarea"
                            placeholder="e.g. Laboratory results reviewed. No further action needed.">{{ old('review_notes') }}</textarea>
                        @error('review_notes')<span class="lr-field-error">{{ $message }}</span>@enderror
                    </div>

                    <label class="lr-check">
                        <input type="checkbox" name="follow_up_recommended" value="1" id="followUpCheckbox"
                               @checked(old('follow_up_recommended'))>
                        <span>Follow-up consultation recommended</span>
                    </label>

                    <div class="lr-form-group" id="followUpInstructionsGroup" style="{{ old('follow_up_recommended') ? '' : 'display:none;' }}">
                        <label class="lr-label" for="follow_up_instructions">
                            Follow-up Instructions <span class="lr-required">*</span>
                        </label>
                        <textarea name="follow_up_instructions" id="follow_up_instructions" class="lr-textarea"
                            placeholder="e.g. Please schedule a follow-up consultation to discuss your laboratory results.">{{ old('follow_up_instructions') }}</textarea>
                    </div>
                    @error('follow_up_instructions')<span class="lr-field-error">{{ $message }}</span>@enderror

                    <div class="lr-actions">
                        @if($laboratoryRequest->isResultReceived())
                            <button type="submit" class="lr-btn lr-btn--primary"
                                    onclick="return confirm('Mark this laboratory result as reviewed?')">
                                <i class='bx bx-check'></i> Mark as Reviewed
                            </button>
                        @endif
                        @if($correctableTests->isNotEmpty())
                            <button type="button" class="lr-btn lr-btn--danger" onclick="openCorrectionModal()">
                                <i class='bx bx-error'></i> Request Correction
                            </button>
                        @endif
                    </div>
                </form>

            @else
                <div class="lr-empty">
                    <i class='bx bx-time-five'></i>
                    <p>Waiting for the laboratory result to be uploaded before it can be reviewed.</p>
                </div>
            @endif
        </div>

        {{-- FOLLOW-UP APPOINTMENTS --}}
        @if($laboratoryRequest->follow_up_recommended)
        <div class="lr-card">
            <div class="lr-card__head">
                <div class="lr-card__icon lr-card__icon--blue"><i class='bx bx-calendar-check'></i></div>
                <h2>Follow-up Appointments</h2>
            </div>

            @forelse($laboratoryRequest->followUpAppointments->sortByDesc('created_at') as $fuAppointment)
                <div class="lr-followup">
                    <div class="lr-followup__ref">{{ $fuAppointment->reference_no }}</div>
                    <div class="lr-followup__details">
                        <span class="lr-followup__date">
                            {{ $fuAppointment->appointment_date?->format('M d, Y') }} · {{ $fuAppointment->formatted_time }}
                        </span>
                        <span class="lr-pill lr-pill--purple">{{ $fuAppointment->status }}</span>
                    </div>
                </div>
            @empty
                <div class="lr-empty lr-empty--sm">
                    <i class='bx bx-calendar-x'></i>
                    <p>No follow-up appointment has been booked yet.</p>
                </div>
            @endforelse
        </div>
        @endif
    </div>

    {{-- ================= CORRECTION HISTORY ================= --}}
    @if($laboratoryRequest->corrections->isNotEmpty())
    <div class="lr-card lr-card--spaced">
        <div class="lr-card__head">
            <div class="lr-card__icon lr-card__icon--red"><i class='bx bx-history'></i></div>
            <h2>Correction History</h2>
        </div>

        @foreach($laboratoryRequest->corrections->sortByDesc('created_at') as $correction)
            <div class="lr-history-item">
                <div class="lr-history-item__top">
                    <span class="lr-pill {{ $correction->isPending() ? 'lr-pill--red' : 'lr-pill--green' }}">
                        {{ $correction->isPending() ? 'Pending' : 'Resolved' }}
                    </span>
                    <span class="lr-history-item__date">{{ $correction->created_at->format('F d, Y g:i A') }}</span>
                </div>
                <p class="lr-history-item__reason">
                    "{{ $correction->reason }}"
                    @if($correction->test_name)
                        <span class="lr-history-item__test">({{ $correction->test_name }})</span>
                    @endif
                </p>
                <p class="lr-history-item__meta">
                    Requested by Dr. {{ $correction->requestedBy->first_name ?? '' }} {{ $correction->requestedBy->last_name ?? '' }}
                    @if($correction->isResolved())
                        · Resolved {{ $correction->resolved_at?->format('F d, Y g:i A') }}
                        @if($correction->resolvingResult)
                            with <a href="{{ $correction->resolvingResult->url }}" target="_blank">{{ $correction->resolvingResult->original_filename }}</a>
                        @endif
                    @endif
                </p>
            </div>
        @endforeach
    </div>
    @endif

</div>

{{-- ================= REQUEST CORRECTION MODAL ================= --}}
<div class="lr-modal" id="correctionModal" onclick="if (event.target === this) closeCorrectionModal()">
    <div class="lr-modal__box">
        <div class="lr-card__head">
            <div class="lr-card__icon lr-card__icon--red"><i class='bx bx-error-circle'></i></div>
            <h2>Request Laboratory Result Correction</h2>
        </div>

        <form action="{{ route('doctor.laboratory-requests.request-correction', $laboratoryRequest->id) }}" method="POST">
            @csrf

            <div class="lr-form-group">
                <label class="lr-label">Which test needs correction? <span class="lr-required">*</span></label>
                <select name="test_name" required class="lr-select">
                    <option value="" disabled selected>— Select a test —</option>
                    @foreach($laboratoryRequest->tests as $test)
                        @continue(($pendingByTest[$test->test_name] ?? null) !== null)
                        <option value="{{ $test->test_name }}" @selected(old('test_name') === $test->test_name)>{{ $test->test_name }}</option>
                    @endforeach
                </select>
                @error('test_name')<span class="lr-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="lr-form-group">
                <label class="lr-label">
                    Reason <span class="lr-required">*</span>
                    <span class="lr-label__hint">(internal — not shown to the patient)</span>
                </label>
                <textarea name="reason" rows="4" required class="lr-textarea"
                    placeholder="e.g. Mali po yung CBC result.">{{ old('reason') }}</textarea>
                @error('reason')<span class="lr-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="lr-modal__actions">
                <button type="button" class="lr-btn lr-btn--cancel" onclick="closeCorrectionModal()">Cancel</button>
                <button type="submit" class="lr-btn lr-btn--danger">
                    <i class='bx bx-error'></i> Request Correction
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const cb = document.getElementById('followUpCheckbox');
    const group = document.getElementById('followUpInstructionsGroup');
    if (!cb || !group) return;
    cb.addEventListener('change', function () {
        group.style.display = cb.checked ? '' : 'none';
    });
})();

function openCorrectionModal()  { document.getElementById('correctionModal').classList.add('show'); }
function closeCorrectionModal() { document.getElementById('correctionModal').classList.remove('show'); }

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeCorrectionModal();
});
</script>

@endsection