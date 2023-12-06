@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7">
            <div class="card">
                <center>
                    <img src="https://wompi.sv/img/logo.svg" alt="" width="100px">
                    <img src="{{ asset('img/smartpyme.png') }}" alt="" width="60px">
                </center>
                <h2 class="text-center">¡Felicitaciones!<br> tu pago está confirmado</h2>

                <p class="text-center">Información del pago:</p>
                <table class="table table-bordered">
                    <tr>
                        <td><b>ID de Venta: </b><br> {{ $venta->id }}</td>
                        <td><b>Detalle: </b><br> {!! $venta->detalleText() !!} </td>
                        <td><b>Total: </b><br> ${{ number_format($venta->total_venta,2) }} </td>
                    </tr>
                    <tr>
                        <td colspan="3"><b>ID de Pago: </b><br> {{ $venta->id_wompi_transaccion }}</td>
                    </tr>
                </table>

                <br>

                <p class="text-center text-secondary">
                    Registra y recibe todos tus pagos de manera fácil  y segura de tu negocio con Wompi y SmartPyme.
                </p>

                <div class="d-flex justify-content-center">
                    <a href="https://www.instagram.com/smartpyme.sv" target="_blank" class="btn btn-danger"><i class="fa fa-instagram"></i></a>
                    <a href="https://www.facebook.com/smartpyme.sv" target="_blank" class="btn btn-primary mx-3"><i class="fa fa-facebook"></i></a>
                    <a href="https://api.whatsapp.com/send/?phone=50377325932" target="_blank" class="btn btn-success mr-3"><i class="fa fa-whatsapp"></i></a>
                    <a href="https://www.smartpyme.sv/" target="_blank" class="btn btn-secondary"><i class="fa fa-globe"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
