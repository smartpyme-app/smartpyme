@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="font-weight-bold text-primary"><i class="fa-solid fa-circle-plus"></i> Nuevo documento</div>

                <div class="mt-2">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @include('documentos.form')

                </div>
            </div>
        </div>
    </div>
</div>
<a href="{{Route('venta.crear')}}" class="btn-flotante btn-blue p-2"><i class="fas fa-edit mr-2"></i>Crear venta</a>
@endsection