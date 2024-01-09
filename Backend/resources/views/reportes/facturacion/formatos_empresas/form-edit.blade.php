  <form class="row g-3" action="{{Route('documento.update')}}" method="post" enctype="multipart/form-data">
    @csrf
  <input type="hidden" name="id" value="{{Crypt::encrypt($documento->id)}}">
  <div class="col-md-4">
    <label for="Nombre" class="form-label font-weight-bold">Tipo documento</label>
    <select class="form-select" name="nombre" required>
      <option value="Factura" {{$documento->nombre ==  'Factura' ? 'selected' : ''}}>Factura</option>
      <option value="Crédito fiscal" {{$documento->nombre == 'Crédito fiscal' ? 'selected' : '' }}>Crédito fiscal</option>
      <option value="Ticket" {{$documento->nombre ==  'Ticket' ? 'selected' : ''}}>Ticket</option>
    </select>
  </div>
  <div class="col-md-3">
    <label for="Correlativo" class="form-label font-weight-bold">Correlativo</label>
    <input type="number" class="form-control" name="correlativo" value="{{$documento->correlativo}}" required>
  </div>
  <div class="col-md-5">
    <label for="Rangos" class="form-label">Serie</label>
    <input type="text" class="form-control" name="rangos" value="{{$documento->rangos}}">
  </div>
  <div class="col-md-4">
    <label for="Número autorización" class="form-label">Número autorización</label>
    <input type="text" class="form-control" name="numero_autorizacion" value="{{$documento->numero_autorizacion}}">
  </div>
  <div class="col-md-4">
    <label for="Número autorización" class="form-label">Resolución</label>
    <input type="text" class="form-control" name="resolucion" value="{{$documento->resolucion}}">
  </div>
  <div class="col-md-4">
    <label for="Número autorización" class="form-label">Fecha</label>
    <input type="date" class="form-control" name="fecha" value="{{$documento->fecha}}">
  </div>
  <div class="col-md-12">
    <label for="Número autorización" class="form-label">Nota</label>
    <textarea class="form-control" name="nota" cols="30" rows="4" placeholder="Aparecerá a la hora de imprimir el documento">{{$documento->nota}}</textarea>
  </div>
  
  <div class="col-12">
   <a class="btn btn-secondary" href="{{Route('documentos')}}">Cancelar</a>
    <button type="submit" class="btn btn-primary float-end">Guardar</button>
  </div>
</form>