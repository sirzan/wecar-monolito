<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nuevo mensaje de contacto</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f5f2; padding: 24px; color: #111827;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px; border: 1px solid #eee7e0;">
        <h1 style="font-size: 22px; margin: 0 0 16px; color: #111827;">Nuevo mensaje de contacto</h1>
        <p style="margin: 0 0 8px;"><strong>Nombre:</strong> {{ $data['name'] }}</p>
        <p style="margin: 0 0 8px;"><strong>Email:</strong> {{ $data['email'] }}</p>
        <p style="margin: 0 0 8px;"><strong>Teléfono:</strong> {{ $data['phone'] ?? 'No proporcionado' }}</p>
        <hr style="border: none; border-top: 1px solid #eee7e0; margin: 20px 0;">
        <p style="white-space: pre-line; line-height: 1.6;">{{ $data['message'] }}</p>
        <hr style="border: none; border-top: 1px solid #eee7e0; margin: 20px 0;">
        <p style="font-size: 12px; color: #6b7280; margin: 0;">Enviado desde el formulario de contacto de wecar.mx</p>
    </div>
</body>
</html>
