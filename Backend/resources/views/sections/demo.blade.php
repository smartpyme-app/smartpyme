@extends('layout')

@section('content')

@include('navbar')
<div class="wrapper">
    
    <div class="page-header header-filter clear-filter purple-filter" data-parallax="true" style="background-image: url({{asset('img/bg1.jpeg')}}); transform: translate3d(0px, 0px, 0px); height: 25vh;">
    </div>

    <div class="main main-raised">

        <div class="section section-contacts py-4">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <h2 class="text-center title mb-0">Indicaciones</h2>
                        <p class="text-center my-3"> Prueba el demo gratis del sistema Wanda para restaurantes. </p>

                        <hr>
                    </div>

                    <div class="col-11 col-md-8">
                        <ol class="list-group ml-3">
                          <li>Ingresa en el botón <b>"Probar demo"</b>.</li>
                          <li>El sistema se puede probar de tres formas:
                            <ul>
                                <li>Ingresando como <b>administrador</b>: el usuario es admin y la contraseña admin.</li>
                                <li>Ingresando como <b>cajero</b>: el usuario es cajero y la contraseña emple.</li>
                                <li>Ingresando como <b>vendedor</b>: el usuario es vendedor y la contraseña emple.</li>
                            </ul>
                        </ol>
                        <p class="my-4"><b>Nota: </b> <br>
                            Todos los datos dentro del sistema han sido generados de forma aleatoria solo para fines demostrativos.
                        </p>
                        <hr>
                        <div class="form-group text-center mt-4">
                          <a href="https://websis.me/wanda/demo-gratis" target="_blank" class="btn btn-primary btn-raised">
                            Probar demo
                          </a>
                        </div>
                    </div>
                </div>
            </div>
      </div>

    </div>

    @include('footer')
</div>

@endsection