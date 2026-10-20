<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $leads = CarLead::query()
            ->with(['car.brand', 'car.model', 'agency'])
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Leads', [
            'leads' => $leads->through(fn (CarLead $lead) => [
                'id' => $lead->getKey(),
                'contact_name' => $lead->contact_name,
                'contact_phone' => $lead->contact_phone,
                'contact_email' => $lead->contact_email,
                'message' => $lead->message,
                'status' => $lead->status,
                'notes' => $lead->notes,
                'car' => trim(($lead->car?->brand?->name ?? '').' '.($lead->car?->model?->name ?? '')),
                'agency' => $lead->agency?->name,
                'created_at' => $lead->created_at?->toIso8601String(),
            ]),
            'filters' => ['status' => $status],
            'statuses' => ['new', 'contacted', 'negotiating', 'won', 'lost'],
        ]);
    }

    public function update(Request $request, CarLead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'contacted', 'negotiating', 'won', 'lost'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead->update($validated);

        return back()->with('success', 'Lead actualizado.');
    }
}
