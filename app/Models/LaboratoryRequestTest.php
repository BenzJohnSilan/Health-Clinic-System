<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaboratoryRequestTest extends Model
{
    protected $table = 'laboratory_request_tests';

    protected $fillable = [
        'laboratory_request_id',
        'test_name',
    ];

    public function laboratoryRequest()
    {
        return $this->belongsTo(LaboratoryRequest::class, 'laboratory_request_id');
    }
}
