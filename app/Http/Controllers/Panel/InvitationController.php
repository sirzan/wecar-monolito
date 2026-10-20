<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AgencyMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $member = AgencyMember::query()
            ->where('token', $token)
            ->with('agency')
            ->firstOrFail();

        if (! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->guest(route('login'));
        }

        abort_unless(strcasecmp($request->user()->email, $member->invited_email) === 0, 403);

        $member->update([
            'user_id' => $request->user()->getKey(),
            'status' => 'active',
            'accepted_at' => now(),
            'token' => null,
        ]);

        $request->session()->put('current_agency_id', $member->agency_id);

        return redirect()
            ->route('panel.agency.dashboard', $member->agency)
            ->with('success', 'Te uniste a la agencia.');
    }
}
