<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user1_id', 'user2_id', 'last_message', 'last_message_at', 'user1_archived', 'user2_archived'];

    protected $casts = [
        'last_message_at' => 'datetime',
        'user1_archived' => 'boolean',
        'user2_archived' => 'boolean',
    ];

    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
