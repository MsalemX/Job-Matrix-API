<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = ['user_id', 'bio', 'avatar', 'points', 'allow_direct_add'];

    protected $casts = [
        'allow_direct_add' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function skills()
    {
        return $this->hasMany(Skill::class);
    }
}
