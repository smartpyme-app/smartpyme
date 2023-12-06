@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-8 col-md-6 col-lg-4">
            @if(session()->has('message'))
                <div class="alert {{session('alert') ?? 'alert-info'}} d-flex justify-content-between" role="alert">
                  <div>
                   {{ session('message') }}
                  </div>
                  <button type="button" class="btn-close float-right" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            <div class="card p-3">
                <div class="card-body text-center">
                   <h4>ID: {{ $transaccion['idEnlace'] }}</h4>
                   <h6>Total: ${{ number_format($venta->total,2) }}</h6>
                   QR:
                   <img onclick="copyToClipboard('{{ $transaccion['urlQrCodeEnlace'] }}')" src="{{ $transaccion['urlQrCodeEnlace'] }}" alt="" class="img-fluid">
                   Enlace: 
                   <div class="input-group">
                       <input type="text" class="form-control" value="{{ $transaccion['urlEnlace'] }}">
                        <a class="input-group-text text-decoration-none" href="{{ $transaccion['urlEnlace'] }}" onclick="copyToClipboard('{{ $transaccion['urlEnlace'] }}')" target="_blank" title="Ir al  enlace"><i class="fa fa-arrow-up-right-from-square text-primary"></i></a>
                        <a class="input-group-text text-decoration-none" href="#" onclick="copyToClipboard('{{ $transaccion['urlEnlace'] }}')" title="Copiar enlace"><i class="fa fa-copy text-primary"></i></a>
                   </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="text/javascript">

    function copyToClipboard(str) {

        if (!navigator && !navigator.clipboard && !navigator.clipboard.writeText)
            alert("The Clipboard API is not available.");

        navigator.clipboard.writeText(str);
        console.log('Copiado');
        Swal.fire({
            title: 'Genial!',
            text: 'Enlace copiado',
            icon: 'success',
            showConfirmButton: false,
            timer: 2000,
        });
    };
    
</script>

@endsection
