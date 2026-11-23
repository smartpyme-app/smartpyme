<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Receta #{{ $receta->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>{{ $empresa->nombre ?? 'Clínica' }}</h1>
    <p class="muted">Receta clínica · {{ $receta->fecha?->format('d/m/Y') }}</p>

    <p><strong>Paciente:</strong>
        @if(($paciente->tipo ?? '') === 'HUMANO')
            {{ trim(($paciente->nombres ?? '').' '.($paciente->apellidos ?? '')) }}
        @else
            {{ $paciente->nombre ?? '—' }}
        @endif
    </p>
    <p><strong>Profesional:</strong> {{ $profesional->name ?? '—' }}</p>

    @if($receta->observaciones)
        <p><strong>Observaciones:</strong> {{ $receta->observaciones }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Medicamento</th>
                <th>Dosis</th>
                <th>Frecuencia</th>
                <th>Duración</th>
                <th>Vía</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receta->lineas as $linea)
                <tr>
                    <td>{{ $linea->orden }}</td>
                    <td>{{ $linea->nombre_medicamento }}</td>
                    <td>{{ $linea->dosis }}</td>
                    <td>{{ $linea->frecuencia ?? '—' }}</td>
                    <td>{{ $linea->duracion ?? '—' }}</td>
                    <td>{{ $linea->via ?? '—' }}</td>
                </tr>
                @if($linea->indicaciones)
                    <tr>
                        <td></td>
                        <td colspan="5"><em>{{ $linea->indicaciones }}</em></td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
