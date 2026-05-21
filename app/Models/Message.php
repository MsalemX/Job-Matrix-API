<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'is_read',
        'deleted_by_sender',
        'deleted_by_recipient',
        'is_deleted',
        'reply_to_id'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'deleted_by_sender' => 'boolean',
        'deleted_by_recipient' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }
}
