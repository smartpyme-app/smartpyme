@extends('layout')

@section('content')

@include('navbar')
<div class="wrapper">
    
    <div class="page-header header-filter clear-filter purple-filter" data-parallax="true" style="background-image: url({{asset('/img/bg1.jpeg')}}); transform: translate3d(0px, 0px, 0px); height: 25vh;">
    </div>

    <div class="main main-raised">

        <form class="contact-form" method="POST" action="{{ route('demo') }}">
        <div class="section section-contacts py-4">
          {{ csrf_field() }}
          <div class="container">
              
            <div class="row justify-content-center">
                <div class="col-11 col-md-6">
                    <h2 class="text-center title mb-0">Prueba gratuita</h2>
                    <p class="text-center description my-3"> Recorre nuestro software para gestión de agro servicios totalmente gratis.</p>

                    <p>Ingresa la siguiente información y te daremos las indicaciones:</p>

                    <div class="form-group bmd-form-group">
                        <label class="bmd-label-floating">Nombre:</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="form-group bmd-form-group">
                        <label class="bmd-label-floating">Correo:</label>
                        <input type="email" class="form-control" name="correo" required>
                    </div>
                    <div class="form-group bmd-form-group">
                        <label class="bmd-label-floating">Teléfono:</label>
                        <input type="tel" class="form-control" name="telefono" required>
                    </div>

                    <div class="form-group text-center mt-4">
                      <button class="btn btn-primary btn-raised">
                        Probar demo
                      </button>
                    </div>
                </div>
            </div>
          </div>
        </div>
        </form>
    </div>
@include('footer')
</div>

@endsection