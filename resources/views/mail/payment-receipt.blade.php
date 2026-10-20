<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo de pago</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f5f2; padding: 24px; color: #111827;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px; border: 1px solid #eee7e0;">
        <h1 style="font-size: 22px; margin: 0 0 12px;">Pago confirmado</h1>
        <p style="line-height: 1.6;">Hola {{ $payment->user?->name ?? 'cliente' }}, recibimos tu pago correctamente.</p>
        <table style="width: 100%; margin-top: 20px; border-collapse: collapse; font-size: 14px;">
            <tr><td style="padding: 8px 0; color: #6b7280;">Plan</td><td style="padding: 8px 0; text-align: right; font-weight: 700;">{{ $subscription?->plan?->name ?? 'Plan Wecar' }}</td></tr>
            <tr><td style="padding: 8px 0; color: #6b7280;">Monto</td><td style="padding: 8px 0; text-align: right; font-weight: 700;">${{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td></tr>
            <tr><td style="padding: 8px 0; color: #6b7280;">Método</td><td style="padding: 8px 0; text-align: right;">{{ ucfirst($payment->provider) }}</td></tr>
            <tr><td style="padding: 8px 0; color: #6b7280;">Referencia</td><td style="padding: 8px 0; text-align: right;">{{ $payment->provider_reference ?? $payment->getKey() }}</td></tr>
            <tr><td style="padding: 8px 0; color: #6b7280;">Fecha</td><td style="padding: 8px 0; text-align: right;">{{ $payment->paid_at?->format('d/m/Y H:i') }}</td></tr>
        </table>
        <p style="margin-top: 24px; font-size: 13px; color: #6b7280;">Gracias por confiar en Wecar.</p>
    </div>
</body>
</html>
