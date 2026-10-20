<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nueva solicitud de crédito</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f5f2; padding: 24px; color: #111827;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px; border: 1px solid #eee7e0;">
        <h1 style="font-size: 22px; margin: 0 0 12px;">Nueva solicitud de crédito</h1>
        <p style="margin: 0 0 8px;"><strong>Folio:</strong> {{ $application->folio }}</p>
        <p style="margin: 0 0 8px;"><strong>Cliente:</strong> {{ $application->full_name }}</p>
        <p style="margin: 0 0 8px;"><strong>Email:</strong> {{ $application->email }}</p>
        <p style="margin: 0 0 8px;"><strong>Teléfono:</strong> {{ $application->phone }}</p>
        <p style="margin: 0 0 8px;"><strong>Auto:</strong> {{ $application->car_brand }} {{ $application->car_model }} {{ $application->car_year }}</p>
        <p style="margin: 0 0 8px;"><strong>Precio:</strong> ${{ number_format((float) $application->car_price, 2) }} MXN</p>
        <p style="margin: 0 0 8px;"><strong>Enganche:</strong> ${{ number_format((float) $application->down_payment, 2) }} MXN</p>
        <p style="margin: 0 0 8px;"><strong>Plazo:</strong> {{ $application->term_months }} meses</p>
        <p style="margin: 0 0 8px;"><strong>Financiera:</strong> {{ $application->financiera_name ?? 'Por asignar' }}</p>
        <p style="margin: 0 0 8px;"><strong>Score:</strong> {{ $application->score ?? '—' }}</p>
        <p style="margin: 0 0 8px;"><strong>Riesgo:</strong> {{ $application->risk_level ?? '—' }}</p>
        <p style="margin: 24px 0 8px;"><strong>Documentos:</strong></p>
        <ul style="margin: 0; padding-left: 20px;">
            @foreach ($documents as $document)
                <li style="margin-bottom: 6px;">
                    {{ $document->slot }}:
                    <a href="{{ route('admin.credits.document', [$application, $document->slot]) }}">Ver documento</a>
                </li>
            @endforeach
        </ul>
        <p style="margin-top: 24px; font-size: 13px; color: #6b7280;">Enviado desde Wecar.</p>
    </div>
</body>
</html>
