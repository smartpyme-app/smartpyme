@extends('layouts.app')

@section('content')

	<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="">
                	<a href="#" class="btn btn-primary float-left">Documentos {{$documentos->total()}}</a>
                    <a href="" data-bs-toggle="modal" data-bs-target="#exampleModal2" class="btn btn-outline-primary float-left ml-3"><i class="fa-solid fa-money-bill"></i> Impuestos</a>
                    
                	<a href="{{Route('documento.crear')}}" id="btn-orange" class="btn text-white float-right">Nuevo documento</a>
                </div>

                <div class="mt-2" id="tabla">

                    @include('documentos.documentos')

                </div>
            </div>
        </div>
    </div>
</div>
<a href="{{Route('venta.crear')}}" class="btn-flotante btn-blue p-2"><i class="fas fa-edit mr-2"></i>Crear venta</a>

<form action="{{Route('impuesto.save')}}" method="post" enctype="multipart/form-data">
    @csrf
<div class="modal fade" id="exampleModal2" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-blue font-weight-bold" id="exampleModalLabel"><i class="fa-solid fa-circle-plus"></i> Crear nuevo impuesto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body row">
        <div class="col-md-6">
        <label for="Descripción" class="form-label font-weight-bold">Nombre del impuesto</label>
        <input type="text" class="form-control" name="nombre">
      </div>
      <div class="col-md-6">
         <label for="costo" class="form-label font-weight-bold">Porcentaje</label>
        <div class="input-group mb-3">
          <span class="input-group-text">%</span>
          <input type="number" class="form-control" name="porcentaje" step="any" required>
        </div>
      </div>
      <div class="col-md-12">
        <h4 class="text-center mt-2 mb-3">Impuestos</h4>
          @foreach($impuestos as $impuesto)
            <div class="bg-light m-2 p-2 pt-3 d-flex justify-content-between">
                <h4>{{$impuesto->nombre}} : %{{$impuesto->porcentaje}}</h4>
                <a type="button" href="" onclick="modal(); update({{$impuesto}});" data-bs-toggle="modal" data-bs-target="#exampleModal3" class="float-right pb-0">
                  <i class="fas fa-pencil"></i> Editar
                </a>
            </div>
          @endforeach
      </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </div>
  </div>
</div>
</form>

<form action="{{Route('impuesto.update')}}" method="post" enctype="multipart/form-data">
    @csrf
<div class="modal fade" id="exampleModal3" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-blue font-weight-bold" id="exampleModalLabel"><i class="fa-solid fa-pencil"></i> Editar impuesto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body row">
        <div class="col-md-6">
        <label for="Descripción" class="form-label font-weight-bold">Nombre del impuesto</label>
        <input type="text" class="form-control" name="nombre" id="nombre_impuesto">
        <input type="hidden" class="form-control" id="impuesto" name="id_impuesto">
      </div>
      <div class="col-md-6">
         <label for="costo" class="form-label font-weight-bold">Porcentaje</label>
        <div class="input-group mb-3">
          <span class="input-group-text">%</span>
          <input type="number" class="form-control" id="per_impuesto" name="porcentaje" step="any" required>
        </div>
      </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </div>
  </div>
</div>
</form>
<script type="text/javascript">
    function modal(){
        $('#exampleModal2').modal('hide')
    }

    function update(imp){
        console.log(imp);
        document.getElementById('impuesto').value = imp.id;
        document.getElementById('nombre_impuesto').value = imp.nombre;
        document.getElementById('per_impuesto').value = imp.porcentaje;
    }
</script>
@endsection

