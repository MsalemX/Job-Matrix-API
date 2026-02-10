<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    /**
     * Display a listing of the user's conversations.
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        
        $conversations = Conversation::where('user1_id', $userId)
            ->orWhere('user2_id', $userId)
            ->with(['user1.profile', 'user2.profile'])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($userId) {
                $otherUser = $conversation->user1_id == $userId ? $conversation->user2 : $conversation->user1;
                return [
                    'id' => $conversation->id,
                    'other_user' => $otherUser,
                    'last_message' => $conversation->last_message,
                    'last_message_at' => $conversation->last_message_at,
                    'unread_count' => $conversation->messages()
                        ->where('sender_id', '!=', $userId)
                        ->where('is_read', false)
                        ->count()
                ];
            });

        return response()->json($conversations);
    }

    /**
     * Get or create a conversation with another user and return messages.
     */
    public function show(User $user)
    {
        $authId = auth()->id();
        
        if ($user->id === $authId) {
            return response()->json(['message' => 'You cannot start a conversation with yourself.'], 400);
        }

        $conversation = Conversation::where(function ($query) use ($authId, $user) {
            $query->where('user1_id', $authId)->where('user2_id', $user->id);
        })->orWhere(function ($query) use ($authId, $user) {
            $query->where('user1_id', $user->id)->where('user2_id', $authId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user1_id' => $authId,
                'user2_id' => $user->id
            ]);
        }

        // Mark incoming messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $authId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'conversation' => $conversation->load(['user1.profile', 'user2.profile']),
            'messages' => $conversation->messages()->with('sender')->oldest()->get()
        ]);
    }

    /**
     * Send a message in a conversation.
     */
    public function storeMessage(Request $request, Conversation $conversation)
    {
        $authId = auth()->id();

        // Check if user is part of the conversation
        if ($conversation->user1_id != $authId && $conversation->user2_id != $authId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'content' => 'required|string'
        ]);

        return DB::transaction(function () use ($request, $conversation, $authId) {
            $message = $conversation->messages()->create([
                'sender_id' => $authId,
                'content' => $request->content,
                'is_read' => false
            ]);

            $conversation->update([
                'last_message' => $request->content,
                'last_message_at' => now()
            ]);

            return response()->json($message->load('sender'), 201);
        });
    }
}
