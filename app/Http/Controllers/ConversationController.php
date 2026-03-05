<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'participant_id' => 'required|exists:users,id',
            'type' => 'required|in:direct,project',
            'project_id' => 'required_if:type,project|exists:projects,id|nullable'
        ]);

        $initiatorId = $request->user()->id;
        $participantId = $request->participant_id;
        $type = $request->type;
        $projectId = $request->project_id;

        // Check availability logic? (not requested yet)

        // Find existing conversation
        $query = Conversation::where('type', $type)
            ->where(function($q) use ($initiatorId, $participantId) {
                 $q->where(function($sub) use ($initiatorId, $participantId) {
                     $sub->where('initiator_id', $initiatorId)->where('participant_id', $participantId);
                 })->orWhere(function($sub) use ($initiatorId, $participantId) {
                     $sub->where('initiator_id', $participantId)->where('participant_id', $initiatorId);
                 });
            });
        
        if ($type === 'project' && $projectId) {
            $query->where('project_id', $projectId);
        }

        $existing = $query->first();

        if ($existing) {
            return response()->json(['message' => 'Conversación existente', 'conversation' => $existing]);
        }

        // Create new
        $conversation = Conversation::create([
            'type' => $type,
            'project_id' => $projectId,
            'initiator_id' => $initiatorId,
            'participant_id' => $participantId
        ]);

        return response()->json(['message' => 'Conversación creada', 'conversation_id' => $conversation->id], 201);
    }

    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::where('initiator_id', $userId)
            ->orWhere('participant_id', $userId)
            ->orWhereHas('participants', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with(['initiator:id,name,lastname,role', 'participant:id,name,lastname,role', 'lastMessage'])
            ->latest('updated_at')
            ->paginate(20);

        $mapped = $conversations->getCollection()->map(function ($conv) use ($userId) {
            // Check if it's a group conversation
            if ($conv->is_group) {
                return [
                    'id'          => $conv->id,
                    'name'        => $conv->name,
                    'role'        => 'group',
                    'timestamp'   => $conv->lastMessage?->created_at ?? $conv->created_at,
                    'lastMessage' => $conv->lastMessage?->content ?? 'Inicio de conversación',
                    'unreadCount' => 0,
                    'isOnline'    => false,
                ];
            }

            $otherUser = $conv->initiator_id === $userId ? $conv->participant : $conv->initiator;
            return [
                'id'          => $conv->id,
                'name'        => $otherUser->name . ' ' . $otherUser->lastname,
                'role'        => $otherUser->role,
                'timestamp'   => $conv->lastMessage?->created_at ?? $conv->created_at,
                'lastMessage' => $conv->lastMessage?->content ?? 'Inicio de conversación',
                'unreadCount' => 0,
                'isOnline'    => false,
            ];
        });

        return response()->json([
            'data'       => $mapped,
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page'    => $conversations->lastPage(),
                'total'        => $conversations->total(),
            ]
        ]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        // Authorization check
        $userId = $request->user()->id;
        if ($conversation->initiator_id !== $userId && $conversation->participant_id !== $userId) {
            abort(403, 'Unauthorized');
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'senderId' => (string)$msg->sender_id,
                    'content' => $msg->content,
                    'timestamp' => $msg->created_at,
                    'type' => $msg->type,
                    'isRead' => $msg->is_read,
                ];
            });

        return response()->json(['data' => $messages]);
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $userId = $request->user()->id;
        if ($conversation->initiator_id !== $userId && $conversation->participant_id !== $userId) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'content' => 'required|string',
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $userId,
            'content' => $request->content,
            'type' => 'text', // Handle file upload later if needed
        ]);

        return response()->json(['data' => [
            'id' => $message->id,
            'senderId' => (string)$message->sender_id,
            'content' => $message->content,
            'timestamp' => $message->created_at,
            'type' => $message->type,
            'isRead' => $message->is_read,
        ]], 201);
    }
}
