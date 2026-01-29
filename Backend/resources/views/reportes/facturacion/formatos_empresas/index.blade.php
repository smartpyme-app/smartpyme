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
        <h6 class="font-weight-bold mb-3">Aplicación del impuesto</h6>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="aplica_ventas" id="aplica_ventas_create" value="1" checked>
          <label class="form-check-label" for="aplica_ventas_create">
            Ventas
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="aplica_compras" id="aplica_compras_create" value="1" checked>
          <label class="form-check-label" for="aplica_compras_create">
            Compras
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="aplica_gastos" id="aplica_gastos_create" value="1" checked>
          <label class="form-check-label" for="aplica_gastos_create">
            Gastos
          </label>
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
      <div class="col-md-12">
        <h6 class="font-weight-bold mb-3">Aplicación del impuesto</h6>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="aplica_ventas" id="aplica_ventas_edit" value="1">
          <label class="form-check-label" for="aplica_ventas_edit">
            Ventas
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="aplica_compras" id="aplica_compras_edit" value="1">
          <label class="form-check-label" for="aplica_compras_edit">
            Compras
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="aplica_gastos" id="aplica_gastos_edit" value="1">
          <label class="form-check-label" for="aplica_gastos_edit">
            Gastos
          </label>
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
        
        // Actualizar checkboxes de aplicación
        document.getElementById('aplica_ventas_edit').checked = imp.aplica_ventas !== false && imp.aplica_ventas !== 0;
        document.getElementById('aplica_gastos_edit').checked = imp.aplica_gastos !== false && imp.aplica_gastos !== 0;
        document.getElementById('aplica_compras_edit').checked = imp.aplica_compras !== false && imp.aplica_compras !== 0;
    }
</script>
@endsection

