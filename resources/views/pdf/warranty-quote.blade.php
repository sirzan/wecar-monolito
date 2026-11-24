<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización de garantía {{ $quote->folio }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 32px 36px;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #e5a13a;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .header td { vertical-align: top; }
        .logo { height: 42px; }
        .folio {
            text-align: right;
            font-size: 12px;
            color: #6b7280;
        }
        .folio strong {
            display: block;
            font-size: 18px;
            color: #111827;
        }
        h1 {
            font-size: 17px;
            margin: 0 0 4px;
            color: #111827;
        }
        .subtitle {
            margin: 0 0 18px;
            color: #6b7280;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 8px 0 0;
        }
        .box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .box h2 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            margin: 0 0 8px;
        }
        .box p {
            margin: 0 0 5px;
        }
        .box p span {
            color: #6b7280;
        }
        table.breakdown {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 18px;
        }
        table.breakdown th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 6px;
        }
        table.breakdown td {
            padding: 9px 6px;
            border-bottom: 1px solid #f3f4f6;
        }
        table.breakdown td.amount {
            text-align: right;
            white-space: nowrap;
        }
        table.breakdown tr.total td {
            border-top: 2px solid #e5a13a;
            border-bottom: none;
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            padding-top: 12px;
        }
        .included {
            color: #047857;
            font-weight: bold;
        }
        .includes {
            margin: 0 0 18px;
            padding: 0;
            list-style: none;
        }
        .includes li {
            padding: 5px 0 5px 18px;
            position: relative;
            color: #374151;
        }
        .includes li::before {
            content: "\2713";
            position: absolute;
            left: 0;
            color: #047857;
            font-weight: bold;
        }
        .note {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 11px 14px;
            color: #92400e;
            margin-bottom: 18px;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
            font-size: 9px;
            color: #9ca3af;
            line-height: 1.5;
        }
        .footer strong { color: #6b7280; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if ($logo)
                    <img src="{{ $logo }}" alt="Wecar" class="logo">
                @else
                    <strong style="font-size: 20px;">Wecar</strong>
                @endif
            </td>
            <td class="folio">
                Cotización de garantía
                <strong>{{ $quote->folio }}</strong>
                {{ $quote->created_at?->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <h1>Cotización de Compra Segura y Garantía</h1>
    <p class="subtitle">
        Vigencia de la cotización: {{ $quote->valid_until?->format('d/m/Y') ?? 'No definida' }}
    </p>

    <table class="grid">
        <tr>
            <td>
                <div class="box">
                    <h2>Datos del cliente</h2>
                    <p><span>Nombre:</span> {{ $quote->full_name }}</p>
                    <p><span>Correo:</span> {{ $quote->email }}</p>
                    @if ($quote->phone)
                        <p><span>Teléfono:</span> {{ $quote->phone }}</p>
                    @endif
                </div>
            </td>
            <td>
                <div class="box">
                    <h2>Datos del vehículo</h2>
                    <p><span>Marca:</span> {{ $quote->car_brand }}</p>
                    <p><span>Modelo:</span> {{ $quote->car_model }}@if ($quote->car_version) {{ $quote->car_version }}@endif</p>
                    <p><span>Año:</span> {{ $quote->car_year }}@if ($quote->car_km !== null) &middot; <span>Kilometraje:</span> {{ number_format($quote->car_km, 0, '.', ',') }} km @endif</p>
                </div>
            </td>
        </tr>
    </table>

    <table class="breakdown">
        <thead>
            <tr>
                <th>Concepto</th>
                <th style="text-align: right;">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Precio del auto</td>
                <td class="amount">${{ number_format((float) $quote->car_price, 2, '.', ',') }} {{ $quote->currency }}</td>
            </tr>
            <tr>
                <td>Cargo por servicio de Compra Segura ({{ rtrim(rtrim(number_format((float) $quote->service_fee_rate * 100, 2, '.', ','), '0'), '.') }}%)</td>
                <td class="amount">${{ number_format((float) $quote->service_fee, 2, '.', ',') }} {{ $quote->currency }}</td>
            </tr>
            <tr>
                <td>Inspección mecánica de 160 puntos</td>
                <td class="amount included">Incluida</td>
            </tr>
            <tr>
                <td>Validación legal y de documentos</td>
                <td class="amount included">Incluida</td>
            </tr>
            <tr class="total">
                <td>Inversión total del auto</td>
                <td class="amount">${{ number_format((float) $quote->total, 2, '.', ',') }} {{ $quote->currency }}</td>
            </tr>
        </tbody>
    </table>

    <div class="box" style="margin-bottom: 16px;">
        <h2>Incluye</h2>
        <ul class="includes">
            @foreach (($quote->includes ?? []) as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    <div class="note">
        Esta cotización es informativa y no representa un contrato ni una reserva. Los importes están expresados en pesos mexicanos (MXN) y pueden variar si cambian las condiciones del vehículo o la tasa de servicio vigente. Vigencia hasta {{ $quote->valid_until?->format('d/m/Y') ?? 'nuevo aviso' }}.
    </div>

    <div class="footer">
        <strong>Wecar &middot; Compra Segura y Garantía</strong><br>
        Contacto: {{ $contactEmail }} &middot; https://wecar.mx/garantia<br>
        Folio {{ $quote->folio }} generado el {{ $quote->created_at?->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
