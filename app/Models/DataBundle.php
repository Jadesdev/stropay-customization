<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataBundle extends Model
{
    use HasFactory, SoftDeletes;
    public function network()
    {
        return $this->belongsTo(Network::class);
    }
}
