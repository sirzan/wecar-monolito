<?php

namespace App\Support;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgencyContext
{
    public function current(Request $request): ?Agency
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $routeAgency = $request->route('agency');

        if ($routeAgency instanceof Agency) {
            abort_unless($user->belongsToAgency($routeAgency), 403);

            return $routeAgency;
        }

        $agencies = $this->allFor($request);

        if ($agencies->isEmpty()) {
            return null;
        }

        $sessionId = $request->session()->get('current_agency_id');
        $agency = $agencies->firstWhere('id', $sessionId) ?? $agencies->first();

        $request->session()->put('current_agency_id', $agency->getKey());

        return $agency;
    }

    /**
     * @return Collection<int, Agency>
     */
    public function allFor(Request $request): Collection
    {
        $user = $request->user();

        if (! $user) {
            return collect();
        }

        return $user->accessibleAgencies();
    }
}
