@extends('layout')

@section('content')

<div class="section section-full-screen section-signup header-filter" style="background-image: url('img/city.jpg'); background-size: cover; background-position: top center; height: 100vh;">
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="card card-signup">
                    <form class="form" method="POST" action="{{ route('register') }}">
                        <div class="header header-primary text-center">
                            <h3 class="no-margin">Bienvenido</h3>
                            <p>Gracias por probar Wgas</p>
                        </div>
                        <div class="content">
                            {{ csrf_field() }}
                            <div class="input-group {{ $errors->has('name') ? ' has-error' : '' }}">
                                <span class="input-group-addon">
                                    <i class="material-icons">face</i>
                                </span>
                                <input id="empresa" type="text" class="form-control" name="empresa" value="{{ old('empresa') }}" placeholder="Nombre de su Gasolinera." required autofocus>

                                @if ($errors->has('empresa'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('empresa') }}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="input-group {{ $errors->has('name') ? ' has-error' : '' }}">
                                <span class="input-group-addon">
                                    <i class="material-icons">face</i>
                                </span>
                                <input id="name" type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="Su nombre y apellido" required>
                                @if ($errors->has('name'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="input-group {{ $errors->has('email') ? ' has-error' : '' }}">
                                <span class="input-group-addon">
                                    <i class="material-icons">email</i>
                                </span>
                                <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" placeholder="Su correo electronico" required>

                                @if ($errors->has('email'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="input-group {{ $errors->has('password') ? ' has-error' : '' }}">
                                <span class="input-group-addon">
                                    <i class="material-icons">lock_outline</i>
                                </span>
                                <input id="password" type="password" class="form-control" name="password" placeholder="Contraseña" required>

                                @if ($errors->has('password'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="input-group {{ $errors->has('password_confirmation') ? ' has-error' : '' }}">
                                <span class="input-group-addon">
                                    <i class="material-icons">lock_outline</i>
                                </span>
                                <input id="password" type="password" class="form-control" name="password_confirmation" placeholder="Repita la contraseña" required>

                                @if ($errors->has('password_confirmation'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('password_confirmation') }}</strong>
                                    </span>
                                @endif
                            </div>

                        </div>
                        <div class="footer text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Registrarse
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
