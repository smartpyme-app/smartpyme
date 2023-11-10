@extends('layouts.app')

@section('content')

<div id="login-box" class="bg-white vh-100">
    <div class="container-fluid h-100">
        <div class="row d-flex align-items-center h-100">
            <div class="col-12 col-md-6">
                <div class="card border-0 px-5 mx-0 mx-lg-2 shadow-none">
                    <div class="card-body">
                        <h2>Recupera tu cuenta</h2>
                        <p>
                            No te preocupes, te enviaremos un correo con instrucciones para que la puedas recuperar.
                        </p>
                        <form class="row mt-5" method="POST" action="{{ route('password.request') }}" autocomplete="off">
                        {{ csrf_field() }}

                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                            <label for="email" class="col-md-4 control-label">Correo</label>
                            <input id="email" type="email" class="form-control" name="email" value="{{ $email }}" required autofocus>

                            @if ($errors->has('email'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                            <label for="password" class="col-md-4 control-label">Contraseña</label>
                            <div class="input-group">
                                <input id="password" type="password" class="form-control" name="password" required>
                                <div class="input-group-addon d-flex">
                                    <button tabindex="-1" id="show_password" class="btn border" type="button" onclick="mostrarPassword()"> <i class="fa-solid fa-eye-slash icon"></i> </button>
                                </div>
                            </div>
                            @if ($errors->has('password'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="form-group{{ $errors->has('password_confirmation') ? ' has-error' : '' }}">
                            <label for="password-confirm" class="col-md-4 control-label">Confirma la contraseña</label>
                            <div class="input-group">
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required>
                                <div class="input-group-addon d-flex">
                                    <button tabindex="-1" id="show_password" class="btn border" type="button" onclick="mostrarPassword2()"> <i class="fa-solid fa-eye-slash icon2"></i> </button>
                                </div>
                            </div>
                            @if ($errors->has('password_confirmation'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('password_confirmation') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="d-grid gap-1">
                            <button type="submit" class="btn btn-primary">
                                Cambiar contraseña
                            </button>
                        </div>
                    </form>
                    </div>
                    <div class="bg-white text-center mt-3">
                        <p> <img src="{{ asset('/img/smartpyme.png') }}" width="150px" alt="smartpyme logo"> </p>
                        <p class="my-4">&copy; {{ date('Y') }} SmartPyme</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-8 col-md-6 d-none d-md-flex" style="background-image: url('/assets/img/icon-bg.png'); background-size: 40%; background-position: bottom -100px right -100px; background-color: rgba(23,117,229,0.35)!important; height: 100vh;">
            </div>
        </div>
    </div>
</div>

<script>
    function mostrarPassword(){
            var cambio = document.getElementById("password");
            if(cambio.type == "password"){
                cambio.type = "text";
                $('.icon').removeClass('fa-solid fa-eye-slash').addClass('fa-solid fa-eye');
            }else{
                cambio.type = "password";
                $('.icon').removeClass('fa-solid fa-eye').addClass('fa-solid fa-eye-slash');
            }
    }   

    function mostrarPassword2(){
            var cambio = document.getElementById("password-confirm");
            if(cambio.type == "password"){
                cambio.type = "text";
                $('.icon2').removeClass('fa-solid fa-eye-slash').addClass('fa-solid fa-eye');
            }else{
                cambio.type = "password";
                $('.icon2').removeClass('fa-solid fa-eye').addClass('fa-solid fa-eye-slash');
            }
    }  
</script>

@endsection
