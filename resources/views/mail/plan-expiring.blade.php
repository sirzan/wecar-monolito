<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Tu plan está por vencer</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="color: #0f3d3e;">Hola {{ $recipientName }}</h1>

    <p>
        {{ $subjectName }} vence
        <strong>{{ $daysRemaining <= 0 ? 'hoy' : 'en '.$daysRemaining.' días' }}</strong>
        @if ($expiresAt)
            ({{ $expiresAt->format('d/m/Y') }})
        @endif.
    </p>

    <p>
        Para evitar que tus publicaciones se pausen, renueva tu plan
        <strong>{{ $planName }}</strong>@if ($amount > 0) por ${{ number_format($amount, 2) }} MXN @endif.
    </p>

    <p style="margin: 24px 0;">
        <a href="{{ $renewalUrl }}" style="background: #0f3d3e; color: #fff; padding: 12px 20px; border-radius: 8px; text-decoration: none;">
            Renovar plan
        </a>
    </p>

    <p style="font-size: 12px; color: #6b7280;">Si ya realizaste el pago, ignora este mensaje.</p>
</body>
</html>
