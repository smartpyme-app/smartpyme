  <form class="row g-3" action="{{Route('documento.save')}}" method="post" enctype="multipart/form-data">
    @csrf
  <div class="col-md-4">
    <label for="Nombre" class="form-label font-weight-bold">Tipo documento</label>
    <select class="form-select" name="nombre" required>
      <option value="Factura">Factura</option>
      <option value="Crédito fiscal">Crédito fiscal</option>
      <option value="Ticket">Ticket</option>
    </select>
  </div>
  <div class="col-md-4">
    <label for="Correlativo" class="form-label font-weight-bold">Correlativo</label>
    <input type="number" class="form-control" name="correlativo">
  </div>
  <div class="col-md-4">
    <label for="Rangos" class="form-label">Serie</label>
    <input type="text" class="form-control" placeholder="Opcional" name="rangos">
  </div>
  <div class="col-md-4">
    <label for="Número autorización" class="form-label">Número autorización</label>
    <input type="text" class="form-control" placeholder="Opcional" name="numero_autorizacion">
  </div>

  <div class="col-md-4">
    <label for="Número autorización" class="form-label">Resolución</label>
    <input type="text" class="form-control" name="resolucion" >
  </div>
  <div class="col-md-4">
    <label for="Número autorización" class="form-label">Fecha</label>
    <input type="date" class="form-control" name="fecha">
  </div>
  <div class="col-md-12">
    <label for="Número autorización" class="form-label">Nota</label>
    <textarea class="form-control" name="nota" cols="30" rows="4" placeholder="Aparecerá a la hora de imprimir el documento"></textarea>
  </div>
  
  <div class="col-12">
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a class="btn btn-secondary" href="{{Route('documentos')}}">Cancelar</a>
  </div>
</form>