<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
use App\Services\Game\GameEventService;
use App\Services\Game\GlobalCrisisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private GameEventService $eventService,
        private GlobalCrisisService $crisisService
    ) {}

    /**
     * Get active game events for the current user
     */
    public function getActive(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = GameEvent::where('user_id', $user->id)
            ->whereIn('status', ['warning', 'active', 'escalated'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($e) => $e->toGameState());

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Resolve a game event using a specific action
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => 'required|uuid|exists:game_events,id',
            'action_id' => 'required|string',
        ]);

        $user = $request->user();
        $result = $this->eventService->resolveEvent($user, $request->event_id, $request->action_id);

        if ($result['resolution'] === 'failure') {
            return response()->json([
                'success' => true,
                'data' => [
                    'outcome' => 'failure',
                    'message' => 'The action failed! The situation has escalated.',
                ],
            ]);
        }

        $event = GameEvent::find($request->event_id);

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event->toGameState(),
                'outcome' => 'success',
                'resolution' => $result,
            ],
        ]);
    }

    /**
     * Take an action during a global crisis
     */
    public function takeCrisisAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string',
        ]);

        try {
            $this->crisisService->takeAction($request->user(), $request->action);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Complete the fiber cut minigame
     */
    public function completeFiberMinigame(Request $request): JsonResponse
    {
        $request->validate([
            'success' => 'required|boolean',
        ]);

        $user = $request->user();

        if ($request->success) {
            $this->crisisService->skipCrisis($user);
            return response()->json(['success' => true, 'message' => 'Fiber rerouted successfully!']);
        } else {
            // Penalty for failure
            $user->economy->reputation = max(0, $user->economy->reputation - 15);
            $user->economy->save();
            \App\Models\GameLog::log($user, "💥 MANUAL REDIRECTION FAILED: Severe network instability detected. Reputation tanked.", 'danger', 'network');
            return response()->json(['success' => true, 'message' => 'Redirection failed.']);
        }
    }

    /**
     * Complete the strike negotiation dialogue
     */
    public function completeStrikeNegotiation(Request $request): JsonResponse
    {
        $request->validate([
            'outcome' => 'required|string', // 'raise', 'benefits', 'intimidate', 'scabs'
            'success' => 'required|boolean',
        ]);

        $user = $request->user();
        $outcome = $request->outcome;
        $success = $request->success;

        if ($outcome === 'raise') {
            // Permanent salary increase for all employees
            $user->employees()->each(function ($e) {
                $e->salary *= 1.15;
                $e->save();
            });
            $this->crisisService->skipCrisis($user);
            GameLog::log($user, "🫱‍🫲 NEGOTIATION SUCCESS: You agreed to a 15% across-the-board raise. The strike has ended.", 'success', 'hr');
        } elseif ($outcome === 'benefits') {
            $cost = 15000;
            if (!$user->economy->canAfford($cost)) return response()->json(['success' => false, 'error' => 'Insufficient funds.']);
            $user->economy->debit($cost, "Strike Settlement: Benefit Package", 'hr');
            $user->employees()->each(function ($e) {
                $e->salary *= 1.05;
                $e->save();
            });
            $this->crisisService->skipCrisis($user);
            GameLog::log($user, "🫱‍🫲 NEGOTIATION SUCCESS: A new benefits package was signed. Workers are returning to their posts.", 'success', 'hr');
        } elseif ($outcome === 'intimidate') {
            if ($success) {
                $this->crisisService->skipCrisis($user);
                $user->economy->reputation -= 10;
                $user->economy->save();
                GameLog::log($user, "⚡ STRIKE BROKEN: Your heavy-handed tactics forced the union to back down. Reputation -10.", 'warning', 'hr');
            } else {
                // Crisis continues, maybe extend it?
                GameLog::log($user, "🔥 NEGOTIATION FAILED: Your threats only emboldened the workers! The strike intensifies.", 'critical', 'hr');
            }
        } elseif ($outcome === 'scabs') {
            $cost = 30000;
            if (!$user->economy->canAfford($cost)) return response()->json(['success' => false, 'error' => 'Insufficient funds.']);
            $user->economy->debit($cost, "Replacement Labor (Scabs)", 'hr');
            $user->economy->reputation -= 30;
            $user->economy->save();
            $this->crisisService->skipCrisis($user);
            GameLog::log($user, "🚜 SCAB LABOR HIRED: Replacement workers have broken the strike. Massive reputation loss (-30).", 'critical', 'hr');
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get event history
     */
    public function getHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = GameEvent::where('user_id', $user->id)
            ->whereIn('status', ['resolved', 'failed'])
            ->orderBy('resolved_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($e) => $e->toGameState());

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * File a Post-Mortem Report for a resolved or failed event
     */
    public function postMortem(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'root_cause' => 'required|string',
            'preventative_action' => 'required|string',
        ]);

        $user = $request->user();
        $event = GameEvent::where('user_id', $user->id)
            ->where('id', $id)
            ->whereIn('status', ['resolved', 'failed'])
            ->firstOrFail();

        if ($event->has_post_mortem) {
            return response()->json([
                'success' => false,
                'error' => 'A Post-Mortem has already been filed for this incident.'
            ], 400);
        }

        // Feature 60: Post Mortem
        // Recover 50% of the lost reputation if there was any reputation loss.
        // If there was no rep loss or it was a success, give a small flat bonus.
        
        $repGain = 0;
        if ($event->status->value === 'failed') {
            $repGain = 10; // Recover a flat amount for failed events
        } else {
            $repGain = 5;  // Bonus for learning from success
        }

        // In a more complex version, we could measure if 'root_cause' matches the actual cause.
        // For now, any filed report grants the bonus.
        
        // Award the reputation
        $user->economy->adjustReputation($repGain);
        
        // Mark as completed
        $event->has_post_mortem = true;
        $event->reputation_recovered = $repGain;
        $event->save();

        // Optional: Unlock "Lessons Learned" research points
        $user->economy->addExperience(150);

        \App\Models\GameLog::log($user, "Filed Post-Mortem for {$event->title}. Recovered {$repGain} Reputation.", 'info', 'infrastructure');

        return response()->json([
            'success' => true,
            'data' => $event->toGameState(),
            'message' => "Post-Mortem filed successfully. Gained {$repGain} Reputation.",
        ]);
    }
}
