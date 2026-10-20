<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const ADMIN_ROLES = ['superadmin', 'manager', 'viewer'];

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'role']);

        $users = User::query()
            ->with('roles')
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $term = '%'.trim($q).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereLike('name', $term)
                        ->orWhereLike('email', $term);
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->role($role))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users' => $users->through(fn (User $user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->roles->pluck('name')->values(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'roles' => self::ADMIN_ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasRole('superadmin'), 403);
        abort_if($request->user()->is($user), 403, 'No puedes modificar tu propio usuario.');

        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['in:'.implode(',', self::ADMIN_ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);

        $currentAdminRoles = $user->getRoleNames()->intersect(self::ADMIN_ROLES);

        if ($currentAdminRoles->contains('superadmin') && ! in_array('superadmin', $validated['roles'], true)) {
            $superadmins = User::query()->role('superadmin')->count();

            if ($superadmins <= 1) {
                throw ValidationException::withMessages([
                    'roles' => 'Debe existir al menos un superadmin.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $validated) {
            foreach (self::ADMIN_ROLES as $role) {
                if (! in_array($role, $validated['roles'], true)) {
                    $user->removeRole($role);
                }
            }

            foreach ($validated['roles'] as $role) {
                $user->assignRole($role);
            }

            $user->update(['is_active' => $validated['is_active']]);
        });

        return back()->with('success', 'Usuario actualizado.');
    }
}
