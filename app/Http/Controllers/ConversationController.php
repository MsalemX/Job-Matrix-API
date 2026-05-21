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
        $archived = filter_var($request->query('archived', false), FILTER_VALIDATE_BOOLEAN);
        
        $conversations = Conversation::where(function ($query) use ($userId, $archived) {
                $query->where('user1_id', $userId)
                      ->where('user1_archived', $archived);
            })
            ->orWhere(function ($query) use ($userId, $archived) {
                $query->where('user2_id', $userId)
                      ->where('user2_archived', $archived);
            })
            ->with(['user1.profile', 'user2.profile'])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($userId) {
                $otherUser = $conversation->user1_id == $userId ? $conversation->user2 : $conversation->user1;
                $isArchived = $conversation->user1_id == $userId ? $conversation->user1_archived : $conversation->user2_archived;
                return [
                    'id' => $conversation->id,
                    'other_user' => $otherUser,
                    'last_message' => $conversation->last_message,
                    'last_message_at' => $conversation->last_message_at,
                    'is_archived' => (bool)$isArchived,
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

        $conversationData = $conversation->load(['user1.profile', 'user2.profile'])->toArray();
        $isArchived = $conversation->user1_id == $authId ? $conversation->user1_archived : $conversation->user2_archived;
        $conversationData['is_archived'] = (bool)$isArchived;

        $messages = $conversation->messages()
            ->where(function ($query) use ($authId) {
                $query->where(function ($q) use ($authId) {
                    $q->where('sender_id', $authId)
                      ->where('deleted_by_sender', false);
                })->orWhere(function ($q) use ($authId) {
                    $q->where('sender_id', '!=', $authId)
                      ->where('deleted_by_recipient', false);
                });
            })
            ->with(['sender', 'replyTo.sender'])
            ->oldest()
            ->get();

        return response()->json([
            'conversation' => $conversationData,
            'messages' => $messages
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
            'content' => 'required|string',
            'reply_to_id' => 'nullable|exists:messages,id'
        ]);

        return DB::transaction(function () use ($request, $conversation, $authId) {
            $message = $conversation->messages()->create([
                'sender_id' => $authId,
                'content' => $request->content,
                'reply_to_id' => $request->reply_to_id,
                'is_read' => false
            ]);

            // Unarchive for both users when a new message is sent
            $conversation->update([
                'last_message' => $request->content,
                'last_message_at' => now(),
                'user1_archived' => false,
                'user2_archived' => false,
            ]);

            return response()->json($message->load(['sender', 'replyTo.sender']), 201);
        });
    }

    /**
     * Toggle archive status for the conversation.
     */
    public function toggleArchive(Request $request, Conversation $conversation)
    {
        $userId = auth()->id();

        if ($conversation->user1_id != $userId && $conversation->user2_id != $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'archive' => 'required|boolean'
        ]);

        $archive = $request->input('archive');

        if ($conversation->user1_id == $userId) {
            $conversation->update(['user1_archived' => $archive]);
        } else {
            $conversation->update(['user2_archived' => $archive]);
        }

        return response()->json([
            'success' => true,
            'archived' => (bool)$archive
        ]);
    }

    /**
     * Delete the conversation.
     */
    public function destroy(Conversation $conversation)
    {
        $userId = auth()->id();

        if ($conversation->user1_id != $userId && $conversation->user2_id != $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete the conversation row. Database constraint cascadeOnDelete
        // will automatically clean up all messages.
        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.'
        ]);
    }

    /**
     * Delete multiple messages.
     */
    public function deleteMessages(Request $request)
    {
        $userId = auth()->id();

        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id',
            'delete_type' => 'required|string|in:me,everyone'
        ]);

        $messageIds = $request->input('message_ids');
        $deleteType = $request->input('delete_type');

        $messages = Message::whereIn('id', $messageIds)->get();

        foreach ($messages as $message) {
            $conversation = $message->conversation;
            if (!$conversation) {
                continue;
            }
            if ($conversation->user1_id != $userId && $conversation->user2_id != $userId) {
                continue;
            }

            if ($deleteType === 'everyone') {
                if ($message->sender_id == $userId) {
                    $message->update([
                        'is_deleted' => true,
                        'content' => 'This message was deleted'
                    ]);

                    if ($conversation->messages()->latest()->first()?->id == $message->id) {
                        $conversation->update(['last_message' => 'This message was deleted']);
                    }
                }
            } else {
                if ($message->sender_id == $userId) {
                    $message->update(['deleted_by_sender' => true]);
                } else {
                    $message->update(['deleted_by_recipient' => true]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Messages deleted successfully.'
        ]);
    }
}
