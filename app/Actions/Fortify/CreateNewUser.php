<?php

namespace App\Actions\Fortify;

use App\Models\Agency;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'account_type' => ['nullable', Rule::in(['personal', 'agency'])],
            'agency_name' => ['nullable', 'required_if:account_type,agency', 'string', 'min:2', 'max:160'],
            'agency_phone' => ['nullable', 'required_if:account_type,agency', 'string', 'min:10', 'max:30'],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
            ]);

            $isAgency = ($validated['account_type'] ?? 'personal') === 'agency';

            Profile::create([
                'user_id' => $user->getKey(),
                'full_name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'is_agency' => $isAgency,
            ]);

            if ($isAgency) {
                $agency = Agency::query()->create([
                    'owner_id' => $user->getKey(),
                    'slug' => $this->uniqueAgencySlug($validated['agency_name']),
                    'name' => $validated['agency_name'],
                    'phone' => $validated['agency_phone'],
                    'plan' => 'agencia',
                    'plan_expires_at' => now()->addDays(30),
                    'max_cars' => 30,
                    'is_active' => true,
                ]);

                $user->assignRole('agency_owner');
                $user->forceFill(['phone' => $validated['agency_phone']])->save();

                session(['current_agency_id' => $agency->getKey()]);
            } else {
                $user->assignRole('seller');
            }

            return $user;
        });
    }

    private function uniqueAgencySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'agencia';
        $slug = $base;
        $counter = 1;

        while (Agency::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
