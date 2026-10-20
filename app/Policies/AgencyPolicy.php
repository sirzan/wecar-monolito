<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;

class AgencyPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function view(User $user, Agency $agency): bool
    {
        return $user->belongsToAgency($agency);
    }

    public function update(User $user, Agency $agency): bool
    {
        return in_array($user->agencyRole($agency), ['owner', 'admin'], true);
    }

    public function manageTeam(User $user, Agency $agency): bool
    {
        return $user->agencyRole($agency) === 'owner';
    }

    public function manageBilling(User $user, Agency $agency): bool
    {
        return $user->agencyRole($agency) === 'owner';
    }

    public function manageLeads(User $user, Agency $agency): bool
    {
        return $user->belongsToAgency($agency);
    }

    public function delete(User $user, Agency $agency): bool
    {
        return $user->agencyRole($agency) === 'owner';
    }
}
