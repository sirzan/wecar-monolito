<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function view(User $user, Car $car): bool
    {
        return $this->ownsOrManages($user, $car);
    }

    public function update(User $user, Car $car): bool
    {
        return $this->ownsOrManages($user, $car);
    }

    public function delete(User $user, Car $car): bool
    {
        return $this->ownsOrManages($user, $car);
    }

    private function ownsOrManages(User $user, Car $car): bool
    {
        if ($car->owner_id === $user->getKey()) {
            return true;
        }

        return $car->agency_id !== null
            && $car->agency !== null
            && in_array($user->agencyRole($car->agency), ['owner', 'admin'], true);
    }
}
