<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            color: #111;
            font-size: 12px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            border-bottom: 2px solid #f94603;
            padding-bottom: 12px;
        }
        .header-brand {
            display: table-cell;
            vertical-align: middle;
        }
        .header-mark {
            display: inline-block;
            width: 22px;
            height: 22px;
            background: #f94603;
            color: #fff;
            font-weight: bold;
            font-size: 11px;
            text-align: center;
            line-height: 22px;
            border-radius: 4px;
            vertical-align: middle;
        }
        .header-name {
            font-weight: bold;
            font-size: 15px;
            margin-left: 6px;
            vertical-align: middle;
        }
        .header-divider {
            margin: 0 10px;
            color: #ccc;
            vertical-align: middle;
        }
        .header-user-logo {
            vertical-align: middle;
        }
        .header-meta {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            font-size: 11px;
            color: #666;
        }
        h1 {
            font-size: 16px;
            margin: 0 0 16px 0;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
        }
        table.items th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #666;
            border-bottom: 1px solid #ccc;
            padding: 6px 8px;
        }
        table.items td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .codigo {
            font-weight: bold;
        }
        .nota {
            color: #444;
            font-style: italic;
        }
        .footer {
            margin-top: 24px;
            font-size: 10px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-brand">
            <span class="header-mark">AC</span>
            <span class="header-name">Auto Peças Center</span>
            @if ($userLogo)
                <span class="header-divider">|</span>
                <img
                    src="{{ $userLogo['uri'] }}"
                    width="{{ $userLogo['width'] }}"
                    height="{{ $userLogo['height'] }}"
                    class="header-user-logo"
                    alt=""
                >
            @endif
        </div>
        <div class="header-meta">
            {{ $quotation->user->name }}<br>
            {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    <h1>{{ $quotation->displayName() }} ({{ $rows->count() }})</h1>

    <table class="items">
        <thead>
            <tr>
                <th>Fabricante</th>
                <th>Código</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['Fabricante'] ?? '—' }}</td>
                    <td class="codigo">{{ $row['Código'] }}</td>
                    <td class="nota">{{ $row['Nota'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Gerado automaticamente pelo Auto Peças Center — {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
