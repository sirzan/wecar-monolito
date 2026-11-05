<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nueva solicitud de revisión</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f5f2; padding: 24px; color: #111827;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px; border: 1px solid #eee7e0;">
        <h1 style="font-size: 22px; margin: 0 0 12px;">Nueva solicitud de revisión</h1>

        <p style="margin: 0 0 8px;"><strong>Folio:</strong> {{ $appointment->folio }}</p>
        <p style="margin: 0 0 8px;"><strong>Cliente:</strong> {{ $appointment->full_name }}</p>
        <p style="margin: 0 0 8px;"><strong>Email:</strong> {{ $appointment->email }}</p>
        <p style="margin: 0 0 8px;"><strong>WhatsApp:</strong> {{ $appointment->whatsapp }}</p>
        <p style="margin: 0 0 8px;"><strong>Estado:</strong> {{ $appointment->state }}</p>
        <p style="margin: 0 0 8px;"><strong>Vehículo:</strong> {{ $appointment->vehicleLabel() }}</p>
        <p style="margin: 0 0 8px;"><strong>Fecha:</strong> {{ $appointment->scheduled_date->format('d/m/Y') }}</p>
        <p style="margin: 0 0 8px;"><strong>Hora:</strong> {{ $appointment->scheduled_time }} ({{ $appointment->timezone }})</p>
        <p style="margin: 0 0 8px;"><strong>Servicio:</strong> {{ $appointment->service }}</p>
        <p style="margin: 0 0 8px;"><strong>Precio:</strong> ${{ number_format((float) $appointment->base_price, 0) }} {{ $appointment->currency }} + IVA</p>
        <p style="margin: 0 0 8px;"><strong>Notas:</strong> {{ $appointment->notes ?: 'Sin notas' }}</p>

        <p style="margin-top: 24px; font-size: 13px; color: #6b7280;">Solicitud registrada desde wecar.mx. Confirma o reprograma desde el panel administrativo.</p>
    </div>
</body>
</html>
