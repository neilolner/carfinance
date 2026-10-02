<?php

namespace App\Http\Middleware;

use App\Models\Feature;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureEnabled
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $feature = Feature::where('key', $featureKey)->first();

        if (! $feature) {
            abort(404, 'Feature not found.');
        }

        $userId = $request->query('user_id');

        if (! $userId) {
            abort(403, 'No user provided.');
        }

        $user = User::find($userId);

        if (! $user || ! $feature->isAccessibleBy($user)) {
            abort(403, 'Feature not enabled for this user.');
        }

        return $next($request);
    }
}
