<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Invitación a {{ $agency->name }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f5f2; padding: 24px; color: #111827;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px; border: 1px solid #eee7e0;">
        <h1 style="font-size: 22px; margin: 0 0 12px; color: #111827;">Te invitaron a {{ $agency->name }}</h1>
        <p style="line-height: 1.6;">Hola,</p>
        <p style="line-height: 1.6;">
            Fuiste invitado a formar parte de <strong>{{ $agency->name }}</strong> en Wecar
            con el rol de <strong>{{ $member->role }}</strong>.
        </p>
        <p style="margin: 24px 0;">
            <a href="{{ $url }}" style="display: inline-block; background: #ff6a14; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 10px; font-weight: 700;">
                Aceptar invitación
            </a>
        </p>
        <p style="font-size: 13px; color: #6b7280; line-height: 1.6;">
            Si el botón no funciona, copia y pega este enlace:<br>
            <span style="word-break: break-all;">{{ $url }}</span>
        </p>
    </div>
</body>
</html>
