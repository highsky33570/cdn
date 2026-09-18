<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkProbeSample extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['probe_cities' => 'array', 'observed_at' => 'immutable_datetime'];
    }
}
