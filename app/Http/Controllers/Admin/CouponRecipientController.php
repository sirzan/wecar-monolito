<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CouponRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponRecipientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Coupons', [
            'recipients' => CouponRecipient::query()->orderByDesc('created_at')->get(),
            'couponCode' => (string) config('external_search.token_coupon_code', 'TKNS50'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
        ]);

        CouponRecipient::query()->updateOrCreate(
            ['email' => mb_strtolower($validated['email'])],
            [
                'coupon_code' => (string) config('external_search.token_coupon_code', 'TKNS50'),
                'created_by' => $request->user()->getKey(),
            ],
        );

        return back()->with('success', 'Correo agregado al cupón.');
    }

    public function destroy(CouponRecipient $couponRecipient): RedirectResponse
    {
        $couponRecipient->delete();

        return back()->with('success', 'Correo eliminado del cupón.');
    }
}
