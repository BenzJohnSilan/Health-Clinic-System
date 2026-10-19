<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory, Archivable;

    /** Key used in archive_entries for this model. */
    public static function archiveType(): string
    {
        return 'service';
    }

    /**
     * Name of the clinic service that holds the Medical Certificate fee
     * (Admin > Services). The fee is billed through the certificate
     * workflow, never as a consultation charge picked by the Doctor.
     */
    public const MEDICAL_CERTIFICATE = 'Medical Certificate';

    /**
     * Name of the clinic service that holds the standard Consultation fee
     * (Admin > Services). It is charged automatically when the Doctor
     * completes the consultation — it is no longer an optional checkbox.
     */
    public const CONSULTATION = 'Consultation';

    protected $fillable = [
        'name',
        'description',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Services the Doctor may tag on a consultation (everything except
     * the Medical Certificate fee).
     */
    public function scopeForConsultation($query)
    {
        return $query->where('name', '!=', self::MEDICAL_CERTIFICATE);
    }

    /**
     * Optional extra services the Doctor can tick: everything except the
     * Medical Certificate fee AND the Consultation fee (which is automatic).
     */
    public function scopeOptionalForConsultation($query)
    {
        return $query->forConsultation()->where('name', '!=', self::CONSULTATION);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
