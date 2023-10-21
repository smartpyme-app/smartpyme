<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Asistencia</title>
</head>

<style>
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    *{ font-family: sans-serif; color: #333; }
    @page { margin: 70px 70px; }
    #logo { position: fixed; top: -20px; left: 0px; width: 80px }
    .header { position: fixed; top: -40px; opacity: .7;  }
    .footer{ position: fixed; bottom: 0px; opacity: .7; }
    .bg{ width: 840px; position: fixed; top: -150px; left: -120px; opacity: .5; z-index: -1;}
    .table {width: 100%; border-collapse: collapse; margin: 100px auto 0px auto;}
    .table-bordered td, .table-bordered th, .table-bordered td, {border: 0.5px solid gray; padding: 5px 10px; }
    hr{ border: 0.5px solid #D1D0D0; }
    .notas>br:before {content: "*"; color: black; }
    p{ text-align: justify; }
    .badge{ background-color: #1B5FFA; color: white; padding: 5px;}
    .completado{text-decoration:line-through; color: gray;}

</style>

<body>
    <img id="logo" src="img/{{$empleados->empresa->logo }}" alt="Logo">
    <div class="header text-center">
        <h2 class="text-center" style="margin-bottom: 0px;">
            {{ $empleados->empresa->nombre }}<br>
            <small>ASISTENCIA DE EMPLEADOS</small>
        </h2>
        <p class="text-center">
            {{ Carbon\Carbon::now()->format('d/m/Y') }}
        </p>
    </div>
    <div class="footer">
        <hr>
        <h4 class="text-center">
            {{ $empleados->empresa->nombre }} | {{ $empleados->empresa->correo }} | {{ $empleados->empresa->telefono }}
        </h4>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th class="text-center">N°</th>
                <th>Nombre empleado</th>
                {{-- <th>Cargo</th> --}}
                <th class="text-center">Entrada</th>
                <th class="text-center">Salida</th>
                <th class="text-center">Total de horas</th>
                {{-- <th class="text-center">Horas Extras</th> --}}
                {{-- <th class="text-center">Horas Totales</th> --}}
            </tr>
        </thead>
        <tbody>
            @foreach ($empleados as $empleado)
                <tr>
                    <td class="text-center">{{ $loop->index + 1 }}</td>
                    <td>{{ $empleado->name }}</td>
                    {{-- <td>{{ $empleado->tipo }}</td> --}}
                    <td  class="text-center">
                    @if ($empleado->entrada)
                        {{ Carbon\Carbon::parse($empleado->entrada)->format('h:i:s a') }}
                    @endif
                    </td>
                    <td  class="text-center">
                    @if ($empleado->salida)
                        {{ Carbon\Carbon::parse($empleado->salida)->format('h:i:s a') }}
                    @endif
                    </td>
                    <td  class="text-center">{{ $empleado->horas }}</td>
                    {{-- <td  class="text-center">{{ $empleado->horas_extras }}</td> --}}
                    {{-- <td  class="text-center">{{ $empleado->horas_laborales }}</td> --}}
                </tr>
            @endforeach
        </tbody>
    </table>
    

</body>
</html>
