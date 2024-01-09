<table class="table align-middle">
  <thead>
    <tr>
      <th scope="col">Tipo documento</th>
      <th scope="col">Correlativo</th>
      
      <th scope="col">Sucursal</th>
      <th scope="col">Activo</th>
      <th scope="col">Predeterminado <span title="Este aparecerá seleccionado de forma predeterminada en el módulo de venta"><i class="fa fa-circle-info"></i></span></th>
      {{-- <th scope="col">Num Autorización</th> --}}
      <th scope="col">Config</th>
    </tr>
  </thead>
  <tbody>
    @foreach($documentos as $documento)
    <tr>
      <th>{{$documento->nombre}}</th>
      <td>{{$documento->correlativo}}</td>
      
      <td>{{$documento->sucursal}}</td>
      <td>@if($documento->activo == false)
          <span class="badge badge-warning">No</span>
          @else
          <span class="badge badge-success">Si</span>
          @endif
      </td>
      <td>
        <form action="{{ Route('documento.predeterminado') }}" method="post">
            @csrf
              <div class="form-check form-switch ml-3">
                <input type="hidden" name="id_documento" value="{{$documento->id}}">
                <input style="cursor: pointer;" onChange="this.form.submit()" class="form-check-input" {{$documento->predeterminado ? 'checked' : ''}} type="checkbox" id="predeterminado{{$documento->id}}" name="predeterminado">
                <label class="form-check-label" for="flexSwitchCheckChecked"> </label>
              </div>
        </form>
      </td>
      
      {{-- <td>{{$documento->numero_autorizacion}}</td> --}}
      <td class="d-flex d-inline">
        <form action="{{Route('documento.delete', Crypt::encrypt($documento->id))}}" method="post">
          @csrf
          @if($documento->activo == true)
                <button type="submit" title="Desactivar" class="btn btn-secondary btn-sm px-3"> <i class="fas fa-ban"></i> </button>
            @else
                <button type="submit" title="Activar" class="btn btn-secondary btn-sm px-3"> <i class="fas fa-check"></i> </button>
            @endif
        </form>
        <a type="button" href="{{Route('documento.edit-form', Crypt::encrypt($documento->id))}}" class="btn btn-secondary btn-sm px-3 ml-2">
          <i class="fas fa-pencil"></i>
        </a>
      </td>
    </tr>
    @endforeach
  </tbody>
</table>

{{$documentos->links()}}
 