<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    // Get list of contacts for the authenticated user
    public function contacts()
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $user = Auth::user();

        if ($user->role === 'owner') {
            // For owners: list tenants who have messaged them or all tenants
            $tenants = User::where('role', 'tenant')->get();
            $contacts = $tenants->map(function ($t) use ($userId) {
                $lastMsg = Message::where(function ($q) use ($userId, $t) {
                    $q->where('sender_id', $userId)->where('receiver_id', $t->id);
                })->orWhere(function ($q) use ($userId, $t) {
                    $q->where('sender_id', $t->id)->where('receiver_id', $userId);
                })->latest()->first();

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'role' => 'tenant',
                    'subtitle' => 'Calon Penyewa',
                    'avatar' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
                    'online' => true,
                    'last_message' => $lastMsg ? $lastMsg->message : 'Mulai obrolan...',
                    'last_time' => $lastMsg ? $lastMsg->created_at->format('H:i') : '',
                ];
            });
        } else {
            // For tenants: list owners with their primary property name
            $owners = User::where('role', 'owner')->with('properties')->get();
            $contacts = $owners->map(function ($o) use ($userId) {
                $lastMsg = Message::where(function ($q) use ($userId, $o) {
                    $q->where('sender_id', $userId)->where('receiver_id', $o->id);
                })->orWhere(function ($q) use ($userId, $o) {
                    $q->where('sender_id', $o->id)->where('receiver_id', $userId);
                })->latest()->first();

                $primaryProp = $o->properties->first();

                return [
                    'id' => $o->id,
                    'name' => $o->name,
                    'role' => 'owner',
                    'subtitle' => $primaryProp ? $primaryProp->name : 'Pemilik Kos',
                    'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&q=80',
                    'online' => true,
                    'last_message' => $lastMsg ? $lastMsg->message : 'Mulai obrolan...',
                    'last_time' => $lastMsg ? $lastMsg->created_at->format('H:i') : '',
                ];
            });
        }

        return response()->json(['contacts' => $contacts]);
    }

    // Get messages with a specific contact
    public function getMessages($receiverId)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $messages = Message::where(function ($q) use ($userId, $receiverId) {
            $q->where('sender_id', $userId)->where('receiver_id', $receiverId);
        })->orWhere(function ($q) use ($userId, $receiverId) {
            $q->where('sender_id', $receiverId)->where('receiver_id', $userId);
        })->orderBy('created_at', 'asc')->get();

        $formatted = $messages->map(function ($m) use ($userId) {
            return [
                'id' => $m->id,
                'sender' => $m->sender_id === $userId ? 'me' : 'other',
                'sender_id' => $m->sender_id,
                'receiver_id' => $m->receiver_id,
                'text' => $m->message,
                'time' => $m->created_at->format('H:i'),
            ];
        });

        return response()->json(['messages' => $formatted]);
    }

    // Send a message
    public function send(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
            'property_id' => 'nullable|exists:properties,id',
        ]);

        $message = Message::create([
            'sender_id' => $userId,
            'receiver_id' => $request->receiver_id,
            'property_id' => $request->property_id,
            'message' => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'sender' => 'me',
                'sender_id' => $userId,
                'receiver_id' => $message->receiver_id,
                'text' => $message->message,
                'time' => $message->created_at->format('H:i'),
            ]
        ]);
    }
}
