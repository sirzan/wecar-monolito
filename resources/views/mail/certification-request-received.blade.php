<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibimos tu solicitud de revisión</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f5f2; padding: 24px; color: #111827;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px; border: 1px solid #eee7e0;">
        <h1 style="font-size: 22px; margin: 0 0 8px; color: #111827;">Recibimos tu solicitud de revisión</h1>
        <p style="margin: 0 0 20px; color: #4b5563;">Hola {{ $appointment->full_name }}, tu cita quedó <strong>pendiente de confirmación</strong> por el taller.</p>

        <p style="margin: 0 0 8px;"><strong>Folio:</strong> {{ $appointment->folio }}</p>
        <p style="margin: 0 0 8px;"><strong>Servicio:</strong> {{ $appointment->service }}</p>
        <p style="margin: 0 0 8px;"><strong>Vehículo:</strong> {{ $appointment->vehicleLabel() }}</p>
        <p style="margin: 0 0 8px;"><strong>Fecha:</strong> {{ $appointment->scheduled_date->format('d/m/Y') }}</p>
        <p style="margin: 0 0 8px;"><strong>Hora:</strong> {{ $appointment->scheduled_time }} ({{ $appointment->timezone }})</p>
        <p style="margin: 0 0 8px;"><strong>Duración:</strong> {{ $appointment->duration_minutes }} minutos</p>
        <p style="margin: 0 0 8px;"><strong>Dirección:</strong> {{ config('certifications.workshop.address') }}</p>
        <p style="margin: 0 0 8px;"><strong>Precio:</strong> ${{ number_format((float) $appointment->base_price, 0) }} {{ $appointment->currency }} + IVA</p>

        <hr style="border: none; border-top: 1px solid #eee7e0; margin: 20px 0;">
        <p style="margin: 0; font-size: 13px; color: #6b7280;">{{ config('certifications.workshop.note') }} Te contactaremos para confirmar tu cita.</p>
        <p style="margin: 12px 0 0; font-size: 12px; color: #9ca3af;">WeCar — wecar.mx</p>
    </div>
</body>
</html>
