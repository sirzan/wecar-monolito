<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Mail\AgencyInvitation;
use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\Car;
use App\Models\CarLead;
use App\Models\CarStatus;
use App\Support\AgencyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgencyController extends Controller
{
    public function current(Request $request): RedirectResponse
    {
        $agency = app(AgencyContext::class)->current($request);

        if (! $agency) {
            return redirect()->route('panel.dashboard')->with('error', 'Aún no perteneces a una agencia.');
        }

        return redirect()->route('panel.agency.dashboard', $agency);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user()->accessibleAgencies()->isNotEmpty(), 403);

        return Inertia::render('Panel/Agency/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->accessibleAgencies()->isNotEmpty(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
        ]);

        $baseSlug = Str::slug($validated['name']) ?: 'agencia';
        $slug = $baseSlug.'-'.Str::lower(Str::random(6));

        $agency = Agency::query()->create([
            ...$validated,
            'owner_id' => $request->user()->getKey(),
            'slug' => $slug,
            'plan' => 'agencia',
            'plan_expires_at' => now()->addDays(30),
            'max_cars' => 30,
            'is_active' => true,
        ]);

        $request->user()->assignRole('agency_owner');
        $request->user()->profile?->update(['is_agency' => true]);
        $request->session()->put('current_agency_id', $agency->getKey());

        return redirect()
            ->route('panel.agency.dashboard', $agency)
            ->with('success', 'Agencia creada correctamente.');
    }

    public function dashboard(Request $request, Agency $agency): Response
    {
        $this->authorize('view', $agency);

        $cars = $agency->cars()->with(['brand', 'model', 'status'])->latest()->limit(6)->get();
        $leads = $agency->leads()->latest()->limit(5)->get();

        return Inertia::render('Panel/Agency/Dashboard', [
            'agency' => $this->agencyData($agency),
            'stats' => [
                'cars' => $agency->cars()->count(),
                'active_cars' => $agency->cars()->whereHas('status', fn ($query) => $query->where('name', 'en_venta'))->count(),
                'leads' => $agency->leads()->count(),
                'team' => $agency->members()->where('status', 'active')->count(),
            ],
            'recentCars' => $cars->map(fn (Car $car) => [
                'id' => $car->getKey(),
                'slug' => $car->slug,
                'title' => trim(($car->brand?->name ?? '').' '.($car->model?->name ?? '').' '.$car->year),
                'price' => (float) $car->price,
                'status' => $car->status?->label ?? $car->status?->name,
            ])->values(),
            'recentLeads' => $leads->map(fn (CarLead $lead) => [
                'id' => $lead->getKey(),
                'contact_name' => $lead->contact_name,
                'contact_phone' => $lead->contact_phone,
                'status' => $lead->status,
                'created_at' => $lead->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function inventory(Request $request, Agency $agency): Response
    {
        $this->authorize('view', $agency);

        $cars = $agency->cars()
            ->with(['brand', 'model', 'status'])
            ->latest()
            ->paginate(12);

        return Inertia::render('Panel/Agency/Inventory', [
            'agency' => $this->agencyData($agency),
            'cars' => $cars->through(fn (Car $car) => [
                'id' => $car->getKey(),
                'slug' => $car->slug,
                'title' => trim(($car->brand?->name ?? '').' '.($car->model?->name ?? '').' '.$car->year),
                'price' => (float) $car->price,
                'km' => (int) $car->km,
                'image' => $car->image,
                'status_id' => $car->status_id,
                'status' => $car->status?->label ?? $car->status?->name,
                'featured' => (bool) $car->featured,
                'url' => route('autos.show', $car->slug),
            ]),
            'statuses' => CarStatus::query()->orderBy('sort_order')->get(['id', 'name', 'label']),
        ]);
    }

    public function leads(Request $request, Agency $agency): Response
    {
        $this->authorize('view', $agency);

        $leads = CarLead::query()
            ->where('agency_id', $agency->getKey())
            ->with(['car.brand', 'car.model'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Panel/Agency/Leads', [
            'agency' => $this->agencyData($agency),
            'leads' => $leads->through(fn (CarLead $lead) => [
                'id' => $lead->getKey(),
                'contact_name' => $lead->contact_name,
                'contact_phone' => $lead->contact_phone,
                'contact_email' => $lead->contact_email,
                'message' => $lead->message,
                'status' => $lead->status,
                'notes' => $lead->notes,
                'car' => trim(($lead->car?->brand?->name ?? '').' '.($lead->car?->model?->name ?? '')),
                'created_at' => $lead->created_at?->toIso8601String(),
            ]),
            'statuses' => ['new', 'contacted', 'negotiating', 'won', 'lost'],
        ]);
    }

    public function updateLead(Request $request, Agency $agency, CarLead $lead): RedirectResponse
    {
        $this->authorize('manageLeads', $agency);
        abort_unless($lead->agency_id === $agency->getKey(), 404);

        $validated = $request->validate([
            'status' => ['required', 'in:new,contacted,negotiating,won,lost'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead->update($validated);

        return back()->with('success', 'Lead actualizado.');
    }

    public function team(Request $request, Agency $agency): Response
    {
        $this->authorize('view', $agency);

        return Inertia::render('Panel/Agency/Team', [
            'agency' => $this->agencyData($agency),
            'members' => $agency->members()->with('user')->latest()->get()->map(fn (AgencyMember $member) => [
                'id' => $member->getKey(),
                'email' => $member->invited_email,
                'name' => $member->user?->name,
                'role' => $member->role,
                'status' => $member->status,
                'accepted_at' => $member->accepted_at?->toIso8601String(),
            ]),
            'canManage' => $request->user()->can('manageTeam', $agency),
        ]);
    }

    public function invite(Request $request, Agency $agency): RedirectResponse
    {
        $this->authorize('manageTeam', $agency);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,seller'],
        ]);

        $email = Str::lower($validated['email']);

        $member = AgencyMember::query()->updateOrCreate(
            ['agency_id' => $agency->getKey(), 'invited_email' => $email],
            [
                'invited_by' => $request->user()->getKey(),
                'role' => $validated['role'],
                'status' => 'invited',
                'token' => Str::random(64),
                'accepted_at' => null,
            ],
        );

        Mail::to($email)->queue(new AgencyInvitation($agency, $member));

        return back()->with('success', 'Invitación enviada.');
    }

    public function updateMember(Request $request, Agency $agency, AgencyMember $member): RedirectResponse
    {
        $this->authorize('manageTeam', $agency);
        abort_unless($member->agency_id === $agency->getKey(), 404);

        $validated = $request->validate([
            'role' => ['required', 'in:admin,seller'],
            'status' => ['required', 'in:invited,active,disabled'],
        ]);

        $member->update($validated);

        return back()->with('success', 'Miembro actualizado.');
    }

    public function removeMember(Request $request, Agency $agency, AgencyMember $member): RedirectResponse
    {
        $this->authorize('manageTeam', $agency);
        abort_unless($member->agency_id === $agency->getKey(), 404);

        $member->delete();

        return back()->with('success', 'Miembro eliminado.');
    }

    public function profile(Request $request, Agency $agency): Response
    {
        $this->authorize('update', $agency);

        return Inertia::render('Panel/Agency/Profile', [
            'agency' => $this->agencyData($agency),
        ]);
    }

    public function updateProfile(Request $request, Agency $agency): RedirectResponse
    {
        $this->authorize('update', $agency);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'hours' => ['nullable', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'rfc' => ['nullable', 'string', 'max:20'],
        ]);

        $agency->update($validated);

        return back()->with('success', 'Perfil de agencia actualizado.');
    }

    public function billing(Request $request, Agency $agency): Response
    {
        $this->authorize('view', $agency);

        return Inertia::render('Panel/Agency/Billing', [
            'agency' => $this->agencyData($agency),
        ]);
    }

    public function settings(Request $request, Agency $agency): Response
    {
        $this->authorize('update', $agency);

        return Inertia::render('Panel/Agency/Settings', [
            'agency' => $this->agencyData($agency),
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'agency_id' => ['required', 'uuid'],
        ]);

        $agency = $request->user()->accessibleAgencies()->firstWhere('id', $validated['agency_id']);

        abort_if($agency === null, 403);

        $request->session()->put('current_agency_id', $agency->getKey());

        return redirect()->route('panel.agency.dashboard', $agency);
    }

    public function updateCar(Request $request, Agency $agency, Car $car): RedirectResponse
    {
        $this->authorize('update', $agency);
        abort_unless($car->agency_id === $agency->getKey(), 404);

        $validated = $request->validate([
            'status_id' => ['sometimes', 'uuid', 'exists:car_statuses,id'],
            'featured' => ['sometimes', 'boolean'],
        ]);

        $car->update($validated);

        return back()->with('success', 'Auto actualizado.');
    }

    public function destroyCar(Request $request, Agency $agency, Car $car): RedirectResponse
    {
        $this->authorize('update', $agency);
        abort_unless($car->agency_id === $agency->getKey(), 404);

        $car->delete();

        return back()->with('success', 'Auto eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function agencyData(Agency $agency): array
    {
        return [
            'id' => $agency->getKey(),
            'slug' => $agency->slug,
            'name' => $agency->name,
            'logo' => $agency->logo,
            'description' => $agency->description,
            'city' => $agency->city,
            'state' => $agency->state,
            'address' => $agency->address,
            'phone' => $agency->phone,
            'whatsapp' => $agency->whatsapp,
            'hours' => $agency->hours,
            'legal_name' => $agency->legal_name,
            'rfc' => $agency->rfc,
            'plan' => $agency->plan,
            'plan_expires_at' => $agency->plan_expires_at?->toIso8601String(),
            'max_cars' => $agency->max_cars,
            'cars_count' => $agency->cars()->count(),
        ];
    }
}
