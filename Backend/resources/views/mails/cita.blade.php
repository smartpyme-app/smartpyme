<style>
    * {
        font-family: 'Inter', sans-serif;
    }
</style>

<center>
    <img src="{{ asset('img/smartpyme.png') }}" width="200px;">
    <p>San Salvador, El Salvador</p>
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
<p><b>Servicio</b>: {{$evento->nombre_servicio}}</p>
<p><b>Detalles</b>: {{$evento->detalles}}</p>


<br><br><br><br>
<hr>
<p>
    SmartPyme Technologies S.A DE S.V
    <br>
    <a href="{{env('APP_URL')}}">smartpyme.sv</a>
</p>
