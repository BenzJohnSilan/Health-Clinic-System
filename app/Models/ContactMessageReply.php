<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reply an Admin emailed to a Contact Us sender. */
class ContactMessageReply extends Model
{
    protected $fillable = ['contact_message_id', 'user_id', 'subject', 'body', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
