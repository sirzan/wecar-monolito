<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibimos tu solicitud</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f5f2; padding: 24px; color: #111827;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px; border: 1px solid #eee7e0;">
        <h1 style="font-size: 22px; margin: 0 0 12px;">Recibimos tu solicitud de crédito</h1>
        <p style="line-height: 1.6;">Hola {{ $application->full_name }},</p>
        <p style="line-height: 1.6;">
            Tu solicitud fue recibida correctamente. Un asesor revisará tu expediente y te contactará
            para continuar con el trámite.
        </p>
        <p style="margin: 16px 0;"><strong>Folio:</strong> {{ $application->folio }}</p>
        <p style="margin: 16px 0;"><strong>Auto:</strong> {{ $application->car_brand }} {{ $application->car_model }} {{ $application->car_year }}</p>
        <p style="margin: 16px 0;"><strong>Financiera:</strong> {{ $application->financiera_name ?? 'Por asignar' }}</p>
        <p style="line-height: 1.6; margin-top: 24px; font-size: 13px; color: #6b7280;">
            Si tienes dudas, responde a este correo o escríbenos a hola@wecar.mx.
        </p>
    </div>
</body>
</html>
