import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-retaceo',
  templateUrl: './retaceo.component.html'
})
export class RetaceoComponent implements OnInit {

  public retaceo: any = {};
  public compras: any = [];
  public gastos: any = [];
  public distribucion: any = [];
  public detallesCompra: any = [];
  public filtros:any = {};
  
  public gastoTransporte: any = { id_gasto: null, tipo_gasto: 'Transporte', monto: 0 };
  public gastoSeguro: any = { id_gasto: null, tipo_gasto: 'Seguro', monto: 0 };
  public gastoDAI: any = { id_gasto: null, tipo_gasto: 'DAI', monto: 0 };
  public gastoOtros: any = { id_gasto: null, tipo_gasto: 'Otro', monto: 0 };
  
  public loading = false;
  public saving = false;
  public opAvanzadas = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
  ) { }

  ngOnInit() {
    this.inicializarRetaceo();
    this.cargarFiltros();
    this.cargarDatos();

    
    // Si estamos editando un retaceo existente
    if (this.route.snapshot.paramMap.get('id')) {
      this.loading = true;
      this.apiService.read('retaceo/', +this.route.snapshot.paramMap.get('id')!)
        .subscribe(retaceo => {
          this.retaceo = retaceo;
          this.cargarDetallesCompra();
          this.cargarGastosRetaceo();
          this.loading = false;
        }, error => {
          this.alertService.error(error);
          this.loading = false;
        });
    }
  }

  cargarFiltros() {
    this.filtros.id_sucursal = '';
    this.filtros.id_proveedor = '';
    this.filtros.id_usuario = '';
    this.filtros.id_usuario = '';
    this.filtros.id_canal = '';
    this.filtros.id_documento = '';
    this.filtros.id_proyecto = '';
    this.filtros.forma_pago = '';
    this.filtros.dte = '';
    this.filtros.estado = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'fecha';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 10;
  }

  inicializarRetaceo() {
    this.retaceo = {
      fecha: this.apiService.date(),
      id_empresa: this.apiService.auth_user().id_empresa,
      id_sucursal: this.apiService.auth_user().id_sucursal,
      id_usuario: this.apiService.auth_user().id,
      total_gastos: 0,
      total_retaceado: 0,
      incoterm: 'FOB',
      tasa_dai: 0
    };
    
    this.gastoTransporte = { id_gasto: null, tipo_gasto: 'Transporte', monto: 0 };
    this.gastoSeguro = { id_gasto: null, tipo_gasto: 'Seguro', monto: 0 };
    this.gastoDAI = { id_gasto: null, tipo_gasto: 'DAI', monto: 0 };
    this.gastoOtros = { id_gasto: null, tipo_gasto: 'Otro', monto: 0 };
    
    this.distribucion = [];
  }

  cargarDatos() {
    this.loading = true;
    
    this.apiService.getAll('compras', this.filtros).subscribe(compras => { 
      this.compras = compras.data.filter((c: any) => c.estado === 'Pagada' || c.estado === 'Pendiente');
      this.loading = false;
  }, error => {this.alertService.error(error); });
    
    // Cargar gastos (egresos)
    this.apiService.getAll('gastos', this.filtros).subscribe(gastos => {
      this.gastos = gastos.data;
      this.loading = false;
    }, error => {
      this.alertService.error(error);
      this.loading = false;
    });
  }

  cargarDetallesCompra() {
    if (!this.retaceo.id_compra) return;
    
    this.loading = true;
    this.apiService.read('compra/', this.retaceo.id_compra).subscribe(compra => {
      this.detallesCompra = compra.detalles;
      
      // Inicializar la distribución
      this.distribucion = [];
      this.detallesCompra.forEach((detalle: any) => {
        this.distribucion.push({
          id_retaceo: null,
          id_producto: detalle.id_producto,
          id_detalle_compra: detalle.id,
          producto: detalle.producto || { nombre: detalle.descripcion },
          cantidad: detalle.cantidad,
          costo_original: detalle.costo || 0,
          valor_fob: detalle.cantidad * (detalle.costo || 0),
          porcentaje_distribucion: 0,
          monto_transporte: 0,
          monto_seguro: 0,
          monto_dai: 0,
          monto_otros: 0,
          costo_landed: 0,
          costo_retaceado: detalle.costo || 0
        });
      });
      
      this.loading = false;
    }, error => {
      this.alertService.error(error);
      this.loading = false;
    });
  }

  cargarGastosRetaceo() {
    if (!this.retaceo.id) return;
    
    this.apiService.getAll('retaceo_gastos', { id_retaceo: this.retaceo.id }).subscribe(gastos => {
      gastos.forEach((gasto: any) => {
        switch (gasto.tipo_gasto) {
          case 'Transporte':
            this.gastoTransporte = gasto;
            break;
          case 'Seguro':
            this.gastoSeguro = gasto;
            break;
          case 'DAI':
            this.gastoDAI = gasto;
            break;
          case 'Otro':
            this.gastoOtros = gasto;
            break;
        }
      });
      
      this.calcularTotalGastos();
    }, error => {
      this.alertService.error(error);
    });
    
    // Cargar distribución
    this.apiService.getAll('retaceo_distribucion', { id_retaceo: this.retaceo.id }).subscribe(distribucion => {
      this.distribucion = distribucion;
      // Obtener productos
      this.distribucion.forEach((item: any) => {
        this.apiService.read('producto/', item.id_producto).subscribe(producto => {
          item.producto = producto;
        });
      });
    }, error => {
      this.alertService.error(error);
    });
  }

  setGastoMonto(tipo: string) {
    let gasto;
    switch (tipo) {
      case 'Transporte':
        gasto = this.gastos.find((g: any) => g.id === this.gastoTransporte.id_gasto);
        if (gasto) this.gastoTransporte.monto = gasto.total || 0;
        break;
      case 'Seguro':
        gasto = this.gastos.find((g: any) => g.id === this.gastoSeguro.id_gasto);
        if (gasto) this.gastoSeguro.monto = gasto.total || 0;
        break;
      case 'DAI':
        gasto = this.gastos.find((g: any) => g.id === this.gastoDAI.id_gasto);
        if (gasto) this.gastoDAI.monto = gasto.total || 0;
        break;
      case 'Otro':
        gasto = this.gastos.find((g: any) => g.id === this.gastoOtros.id_gasto);
        if (gasto) this.gastoOtros.monto = gasto.total || 0;
        break;
    }
    
    this.calcularTotalGastos();
  }

  calcularTotalGastos() {
    this.retaceo.total_gastos = (
      parseFloat(this.gastoTransporte.monto || 0) + 
      parseFloat(this.gastoSeguro.monto || 0) + 
      parseFloat(this.gastoDAI.monto || 0) + 
      parseFloat(this.gastoOtros.monto || 0)
    ).toFixed(2);
  }

  calcularDistribucion() {
    if (this.distribucion.length === 0) {
      this.alertService.error('No hay productos para distribuir los gastos');
      return;
    }
    
    if (parseFloat(this.retaceo.total_gastos) <= 0) {
      this.alertService.warning('No hay gastos para distribuir', 'Distribución');
      return;
    }
    

    const valorFobTotal = this.distribucion.reduce((sum: number, item: any) => 
      sum + parseFloat(item.valor_fob || 0), 0);
    
    if (valorFobTotal <= 0) {
      this.alertService.error('El valor FOB total debe ser mayor que cero');
      return;
    }
    

    this.distribucion.forEach((item: any) => {

      item.porcentaje_distribucion = ((item.valor_fob / valorFobTotal) * 100).toFixed(2);
      

      item.monto_transporte = ((item.porcentaje_distribucion / 100) * this.gastoTransporte.monto).toFixed(2);
      item.monto_seguro = ((item.porcentaje_distribucion / 100) * this.gastoSeguro.monto).toFixed(2);
      item.monto_dai = ((item.porcentaje_distribucion / 100) * this.gastoDAI.monto).toFixed(2);
      item.monto_otros = ((item.porcentaje_distribucion / 100) * this.gastoOtros.monto).toFixed(2);
      
  
      item.costo_landed = (
        parseFloat(item.valor_fob) + 
        parseFloat(item.monto_transporte) + 
        parseFloat(item.monto_seguro) + 
        parseFloat(item.monto_dai) + 
        parseFloat(item.monto_otros)
      ).toFixed(2);
      

      item.costo_retaceado = (item.costo_landed / item.cantidad).toFixed(2);
    });
    

    this.retaceo.total_retaceado = this.distribucion.reduce((sum: number, item: any) => 
      sum + parseFloat(item.costo_landed || 0), 0).toFixed(2);
    
    this.alertService.success('Distribución calculada correctamente', 'Distribución');
  }

guardarRetaceo() {
  let detalleProductos = '';
  let totalCambio = 0;
  
  this.distribucion.forEach((item: any) => {
    const costoOriginal = parseFloat(item.costo_original);
    const costoRetaceado = parseFloat(item.costo_retaceado);
    const diferencia = costoRetaceado - costoOriginal;
    const nombreProducto = item.producto?.nombre || `Producto #${item.id_producto}`;
    
    detalleProductos += `<tr>
      <td>${nombreProducto}</td>
      <td class="text-center">${item.cantidad}</td>
      <td class="text-end">$${costoOriginal.toFixed(2)}</td>
      <td class="text-end">$${costoRetaceado.toFixed(2)}</td>
      <td class="text-end ${diferencia > 0 ? 'text-success' : 'text-danger'}">$${diferencia.toFixed(2)}</td>
    </tr>`;
    
    totalCambio += diferencia * item.cantidad;
  });
  
  const contenidoHTML = `
    <div class="text-start">
      <p>Esta acción actualizará los costos de los siguientes productos:</p>
      <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>Producto</th>
              <th class="text-center">Cant.</th>
              <th class="text-end">Costo Original</th>
              <th class="text-end">Costo Retaceado</th>
              <th class="text-end">Diferencia</th>
            </tr>
          </thead>
          <tbody>
            ${detalleProductos}
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="text-end fw-bold">Cambio total en inventario:</td>
              <td class="text-end fw-bold ${totalCambio > 0 ? 'text-success' : 'text-danger'}">$${totalCambio.toFixed(2)}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <p class="mt-3 fw-bold">¿Confirma aplicar estos cambios?</p>
    </div>
  `;
  
  Swal.fire({
    title: 'Confirmar Retaceo',
    html: contenidoHTML,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, aplicar retaceo',
    cancelButtonText: 'Cancelar',
    width: '800px'
  }).then((result) => {
    if (result.isConfirmed) {
      this.onSubmit();
    }
  });
}

  onSubmit() {
    this.saving = true;
    
    // Preparar objeto para enviar al servidor
    const datosRetaceo = {
      ...this.retaceo,
      gastos: [
        { ...this.gastoTransporte },
        { ...this.gastoSeguro },
        { ...this.gastoDAI },
        { ...this.gastoOtros }
      ],
      distribucion: this.distribucion
    };
    
    this.apiService.store('retaceo', datosRetaceo).subscribe(response => {
      this.alertService.success('Retaceo aplicado correctamente', 'Retaceo');
      this.router.navigate(['/retaceos']);
      this.saving = false;
    }, error => {
      this.alertService.error(error);
      this.saving = false;
    });
  }

  toggleDiv() {
    this.opAvanzadas = !this.opAvanzadas;
  }

recalcularDistribucion() {

  const totalPorcentaje = this.distribucion.reduce((sum: number, item: any) => 
    sum + parseFloat(item.porcentaje_distribucion || 0), 0);
  

  if (Math.abs(totalPorcentaje - 100) > 0.01) {
    this.alertService.warning(`La suma de porcentajes (${totalPorcentaje.toFixed(2)}%) debe ser 100%. Se normalizarán los valores.`, 'Distribución');
    
    this.distribucion.forEach((item: any) => {
      item.porcentaje_distribucion = ((parseFloat(item.porcentaje_distribucion) / totalPorcentaje) * 100).toFixed(2);
    });
  }
  

  this.distribucion.forEach((item: any) => {
    // Distribuir gastos según porcentaje
    item.monto_transporte = ((item.porcentaje_distribucion / 100) * this.gastoTransporte.monto).toFixed(2);
    item.monto_seguro = ((item.porcentaje_distribucion / 100) * this.gastoSeguro.monto).toFixed(2);
    item.monto_dai = ((item.porcentaje_distribucion / 100) * this.gastoDAI.monto).toFixed(2);
    item.monto_otros = ((item.porcentaje_distribucion / 100) * this.gastoOtros.monto).toFixed(2);
    

    item.costo_landed = (
      parseFloat(item.valor_fob) + 
      parseFloat(item.monto_transporte) + 
      parseFloat(item.monto_seguro) + 
      parseFloat(item.monto_dai) + 
      parseFloat(item.monto_otros)
    ).toFixed(2);
    

    item.costo_retaceado = (item.costo_landed / item.cantidad).toFixed(2);
  });
  

  this.retaceo.total_retaceado = this.distribucion.reduce((sum: number, item: any) => 
    sum + parseFloat(item.costo_landed || 0), 0).toFixed(2);
}
}