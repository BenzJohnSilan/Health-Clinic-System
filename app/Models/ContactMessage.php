<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Scopes\ExcludeArchivedScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An inquiry submitted through the public Contact Us form.
 * Only Admin ever sees these (see Admin\ContactMessageController).
 */
class ContactMessage extends Model
{
    use Archivable;

    /** Key used in archive_entries for this model. */
    public static function archiveType(): string
    {
        return 'contact_message';
    }

    /** Archived messages disappear from every query (list, counts, URLs). */
    protected static function booted(): void
    {
        static::addGlobalScope(new ExcludeArchivedScope());
    }

    public const STATUS_UNREAD = 'unread';
    public const STATUS_READ   = 'read';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'contact_number',
        'message',
        'status',
        'read_at',
        'notified_at',
        'notification_error',
    ];

    protected $casts = [
        'read_at'     => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactMessageReply::class)->orderByDesc('sent_at')->orderByDesc('id');
    }

    public function isUnread(): bool
    {
        return $this->status === self::STATUS_UNREAD;
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNREAD);
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READ);
    }

    public function markAsRead(): void
    {
        if ($this->isUnread()) {
            $this->forceFill(['status' => self::STATUS_READ, 'read_at' => now()])->save();
        }
    }

    public function markAsUnread(): void
    {
        $this->forceFill(['status' => self::STATUS_UNREAD, 'read_at' => null])->save();
    }
}
