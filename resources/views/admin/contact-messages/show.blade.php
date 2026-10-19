@extends('layouts.admin')

@section('head')
{{-- Same design system as the Admin Dashboard (hero card, .adm-card, pills, page background). --}}
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-contact-messages.css') }}">
@endsection

@section('content')

<div class="adm-dash">

    {{-- ================= HERO ================= --}}
    <div class="adm-hero">
        <div class="adm-hero-left">
            <div class="adm-hero-badge">
                <i class='bx {{ $message->isUnread() ? 'bx-envelope' : 'bx-envelope-open' }}'></i>
            </div>
            <div>
                <h2>Message from {{ $message->full_name }}</h2>
                <p>Received {{ $message->created_at->format('F j, Y \a\t g:i A') }}</p>
            </div>
        </div>
        <a href="{{ route('admin.contact-messages.index') }}" class="adm-hero-date cm-hero-link">
            <i class='bx bx-arrow-back'></i> Back to Contact Messages
        </a>
    </div>

    @if(session('success'))
        <div class="cm-alert cm-alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="cm-alert cm-alert-error" role="alert">{{ session('error') }}</div>
    @endif

    <div class="adm-grid-main">

        {{-- ================= MESSAGE ================= --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-message-detail'></i></span>
                    <h3>Message</h3>
                </div>
                @if($message->isUnread())
                    <span class="adm-pill adm-pill--purple"><i class='bx bxs-envelope'></i> Unread</span>
                @else
                    <span class="adm-pill adm-pill--gray"><i class='bx bx-envelope-open'></i> Read</span>
                @endif
            </div>

            <div class="adm-card-body">
                <div class="cm-message-box">{{ $message->message }}</div>
            </div>

            <div class="adm-card-footer cm-footer">
                <form class="cm-inline" method="POST" action="{{ route('admin.contact-messages.unread', $message) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="adm-view-btn"><i class='bx bx-envelope'></i> Mark as Unread</button>
                </form>

                @unless($message->notified_at)
                    <form class="cm-inline" method="POST" action="{{ route('admin.contact-messages.resend', $message) }}">
                        @csrf
                        <button type="submit" class="adm-view-btn"><i class='bx bx-refresh'></i> Resend Notification</button>
                    </form>
                @endunless

                <a class="adm-view-btn cm-solid" href="#reply">
                    <i class='bx bx-reply'></i> Reply
                </a>

                <form class="cm-inline cm-push-right" method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}"
                      onsubmit="return confirm('Move this message to the Archive? Its reply history is kept and you can restore it any time.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="adm-view-btn cm-danger"><i class='bx bx-archive'></i> Archive</button>
                </form>
            </div>
        </div>

        {{-- ================= SIDE COLUMN ================= --}}
        <div class="cm-side">

            <div class="adm-card">
                <div class="adm-card-header">
                    <div class="adm-card-title">
                        <span class="adm-card-icon"><i class='bx bx-user'></i></span>
                        <h3>Sender Details</h3>
                    </div>
                </div>

                <div class="adm-card-body cm-info-list">
                    <div class="cm-info-row">
                        <span class="cm-info-icon"><i class='bx bx-id-card'></i></span>
                        <div class="cm-info-text"><small>Name</small><strong>{{ $message->full_name }}</strong></div>
                    </div>
                    <div class="cm-info-row">
                        <span class="cm-info-icon"><i class='bx bx-envelope'></i></span>
                        <div class="cm-info-text">
                            <small>Email</small>
                            <strong><a href="mailto:{{ $message->email }}">{{ $message->email }}</a></strong>
                        </div>
                    </div>
                    <div class="cm-info-row">
                        <span class="cm-info-icon"><i class='bx bx-phone'></i></span>
                        <div class="cm-info-text">
                            <small>Contact Number</small>
                            <strong><a href="tel:{{ preg_replace('/[^0-9+]/', '', $message->contact_number) }}">{{ $message->contact_number }}</a></strong>
                        </div>
                    </div>
                    <div class="cm-info-row">
                        <span class="cm-info-icon"><i class='bx bx-calendar'></i></span>
                        <div class="cm-info-text">
                            <small>Submitted</small>
                            <strong>{{ $message->created_at->format('M d, Y · g:i A') }}</strong>
                        </div>
                    </div>
                    @if($message->read_at)
                        <div class="cm-info-row">
                            <span class="cm-info-icon"><i class='bx bx-check-double'></i></span>
                            <div class="cm-info-text">
                                <small>Read</small>
                                <strong>{{ $message->read_at->format('M d, Y · g:i A') }}</strong>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="adm-card">
                <div class="adm-card-header">
                    <div class="adm-card-title">
                        <span class="adm-card-icon"><i class='bx bx-mail-send'></i></span>
                        <h3>Email Notification</h3>
                    </div>
                    @if($message->notified_at)
                        <span class="adm-pill adm-pill--green"><i class='bx bx-check-circle'></i> Sent</span>
                    @else
                        <span class="adm-pill adm-pill--red"><i class='bx bx-error-circle'></i> Not sent</span>
                    @endif
                </div>

                <div class="adm-card-body">
                    @if($message->notified_at)
                        <p class="cm-note">Emailed to the clinic on {{ $message->notified_at->format('M d, Y · g:i A') }}.</p>
                    @else
                        <p class="cm-note">
                            {{ $message->notification_error ?: 'The clinic has not been notified by email yet.' }}
                            Use <strong>Resend Notification</strong> to try again.
                        </p>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- ================= REPLY ================= --}}
    <div class="adm-card" id="reply">
        <div class="adm-card-header">
            <div class="adm-card-title">
                <span class="adm-card-icon"><i class='bx bx-reply'></i></span>
                <h3>Reply to {{ $message->first_name }}</h3>
            </div>
            @if($replies->count())
                <span class="adm-pill adm-pill--green"><i class='bx bx-check-circle'></i> Replied</span>
            @else
                <span class="adm-pill adm-pill--gray"><i class='bx bx-time-five'></i> No reply yet</span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.contact-messages.reply', $message) }}" class="adm-card-body cm-form" id="replyForm">
            @csrf

            <div class="cm-form-row">
                <label>To</label>
                <div class="cm-to"><i class='bx bx-envelope'></i> {{ $message->full_name }} &lt;{{ $message->email }}&gt;</div>
            </div>

            <div class="cm-form-row">
                <label for="reply_subject">Subject <span class="cm-req">*</span></label>
                <input type="text" id="reply_subject" name="subject" maxlength="150" required
                       class="cm-input @error('subject') cm-invalid @enderror"
                       value="{{ old('subject', 'Re: Your inquiry to the clinic') }}">
                @error('subject') <span class="cm-field-error">{{ $message }}</span> @enderror
            </div>

            <div class="cm-form-row">
                <label for="reply_body">Message <span class="cm-req">*</span></label>
                <textarea id="reply_body" name="body" rows="7" maxlength="5000" required
                          class="cm-input cm-textarea @error('body') cm-invalid @enderror"
                          placeholder="Write your reply to {{ $message->first_name }}...">{{ old('body') }}</textarea>
                @error('body') <span class="cm-field-error">{{ $message }}</span> @enderror
                <span class="cm-hint">Sent as a branded email with a greeting, your clinic signature and the original message quoted below.</span>
            </div>

            <div class="adm-card-footer cm-footer">
                <button type="submit" class="adm-view-btn cm-solid" id="replySend">
                    <i class='bx bx-send'></i> Send Reply
                </button>
            </div>
        </form>
    </div>

    {{-- ================= REPLY HISTORY ================= --}}
    @if($replies->count())
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-history'></i></span>
                    <h3>Reply History</h3>
                </div>
                <span class="adm-pill adm-pill--purple">{{ $replies->count() }} sent</span>
            </div>

            <div class="adm-card-body cm-history">
                @foreach($replies as $reply)
                    <div class="cm-reply">
                        <div class="cm-reply-head">
                            <strong>{{ $reply->subject }}</strong>
                            <span>{{ $reply->sent_at->format('M d, Y · g:i A') }}@if($reply->sender) · by {{ $reply->sender->first_name }} {{ $reply->sender->last_name }}@endif</span>
                        </div>
                        <div class="cm-reply-body">{{ $reply->body }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>

<script>
    // Lock the button after the first click so a double-click cannot send two emails.
    document.getElementById('replyForm')?.addEventListener('submit', function () {
        var b = document.getElementById('replySend');
        if (b) { b.disabled = true; b.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Sending..."; }
    });
    // After a failed validation / send, bring the reply form into view.
    @if($errors->any() || session('error') || session('success'))
        document.getElementById('reply')?.scrollIntoView({ block: 'center' });
    @endif
</script>

@endsection
