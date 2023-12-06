@extends('layouts.app')

@section('content')
<div class="container mt-5 mb-3 vh-100">
    <div class="row justify-content-center vh-100 align-content-center">
        <div class="col-md-9">
        <div class="card py-4">
            <div class="card-body py-4">
                <h5 class="text-center text-primary">
                    <img class="mb-3" src="{{asset('img/smartpyme.png')}}" height="30px;"> <br>
                    Registro exitoso
                </h5>
                <div class="row justify-content-center">
                    <div class="col-md-8 form-group text-center mt-2 mb-2">
                        <h3 class="mb-3">Tu suscripción se realizó correctamente</h3>                        
                        <h5 class="my-4">
                            <b>{{ $transaccion->cliente }}</b> ya eres parte de la familia SmartPyme
                        </h5>
                        <p class="mb-5">
                            Recuerda que tienes <b>15 días de prueba</b> antes de realizar el cargo automático a tu tarjeta.
                        </p>                    
                        <p>Iniciemos a gestionar tu negocio</p>
                        <div class="row mb-5 justify-content-around">
                            <div class="col">
                                <a class="btn btn-primary btn-lg btn-block" href="{{ env('APP_URL') }}">Ir a mi cuenta</a>      
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
    <footer>
        <div class="container-fluid">
       
          <div class="d-flex d-inline justify-content-center text-secondary pt-2" style="margin-top: 10px; margin-bottom: 10px;">
              
              &copy;
              <script>
                  document.write(new Date().getFullYear())
              </script>
              <p class="text-secondary">, SmartPyme</p>
          </div>
        </div>
    </footer>
</div>
@endsection


