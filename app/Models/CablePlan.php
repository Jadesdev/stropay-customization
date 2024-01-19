<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CablePlan extends Model
{
    use HasFactory, SoftDeletes;
    public function decoder()
    {
        return $this->belongsTo(Decoder::class);
    }
}
