<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['profile_id', 'name'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
