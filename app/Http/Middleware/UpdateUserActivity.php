<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Game\ActivityService;

class UpdateUserActivity
{
    public function __construct(
        protected ActivityService $activityService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $this->activityService->updateLastActive($user);
        }

        return $next($request);
    }
}
