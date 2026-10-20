<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $recipient = data_get(SiteSetting::get('contact_info', []), 'email')
            ?? config('mail.from.address');

        Mail::to($recipient)->queue(new ContactMessage($validated));

        return back()->with('success', 'Mensaje enviado. Te responderemos pronto.');
    }
}
