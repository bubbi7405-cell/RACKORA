<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tickets = SupportTicket::where('user_id', $user->id)
            ->with('customer', 'employee')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($tickets->map->toGameState());
    }

    public function investigate(Request $request, $id)
    {
        $user = $request->user();
        $ticket = SupportTicket::where('user_id', $user->id)->findOrFail($id);
        
        if ($ticket->status === 'resolved') {
            return response()->json(['error' => 'Ticket already resolved'], 400);
        }

        // Mini-game logic or simple boost
        $ticket->status = 'analyzing';
        $ticket->progress = min(100, $ticket->progress + 25);
        $ticket->save();

        return response()->json([
            'success' => true,
            'ticket' => $ticket->toGameState()
        ]);
    }
}
