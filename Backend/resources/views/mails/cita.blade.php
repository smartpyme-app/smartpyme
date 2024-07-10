<style>
    * {
        font-family: 'Inter', sans-serif;
    }
</style>

<center>
    {{-- <img src="{{ asset('img/smartpyme.png') }}" width="200px;"> --}}
    @if ($evento->empresa()->pluck('logo')->first())
        <img height="100" src="{{ asset('img/'.$evento->empresa()->pluck('logo')->first()) }}" alt="Logo">
    @endif
    {{-- <p>San Salvador, El Salvador</p> --}}
</center>

<h3>Hola, tienes una cita confirmada.</h3>
<hr>
<p><b>Descripción</b>: {{$evento->descripcion}}</p>
<p><b>Duración</b>: {{$evento->duracion}}</p>
<p><b>Fecha</b>: {{ \Carbon\Carbon::parse($evento->inicio)->format('d/m/Y') }} </p>
<p><b>Hora</b>: {{ \Carbon\Carbon::parse($evento->inicio)->format('h:i a') }} </p>
{{-- <p><b>Tipo</b>: {{$evento->tipo}}</p> --}}
{{-- <p><b>Cliente</b>: {{$evento->cliente}}</p> --}}
{{-- <p><b>Encargado</b>: {{$evento->encargado}}</p> --}}
{{-- <p><b>Frecuencia</b>: {{ $evento->frecuencia == "YEARLY" ? "Anual" : ($evento->frecuencia == "MONTHLY" ? "Mensual" : ($evento->frecuencia == "WEEKLY" ? "Semanal" : ($evento->frecuencia == "DAILY" ? "Diaria" : ""))) }}</p> --}}
@if ($evento->productos()->count() > 0)
    <p><b>Productos y servicios</b>:</p>
    <ul>
        @foreach ($evento->productos()->get() as $producto)
            <li> {{ $producto->cantidad }} - {{ $producto->nombre_producto }} </li>
        @endforeach
    </ul>
@endif

<p><b>Detalles</b>: {{$evento->detalles}}</p>


<br><br><br><br>
<hr>
<p>
    SmartPyme Technologies S.A DE S.V
    <br>
    <a href="{{env('APP_URL')}}">smartpyme.sv</a>
</p>
