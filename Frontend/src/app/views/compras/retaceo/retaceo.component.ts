import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-retaceo',
  templateUrl: './retaceo.component.html',
  styleUrls: ['./retaceo.component.css'],
})
export class RetaceoComponent implements OnInit {
  public retaceo: any = {};
  public compras: any = [];
  public gastos: any = [];
  public distribucion: any = [];
  public detallesCompra: any = [];
  public filtros: any = {};

  public gastoTransporte: any = {
    id_gasto: null,
    tipo_gasto: 'Transporte',
    monto: 0,
  };
  public gastoSeguro: any = { id_gasto: null, tipo_gasto: 'Seguro', monto: 0 };
  public gastoDAI: any = { id_gasto: null, tipo_gasto: 'DAI', monto: 0 };
  public gastoOtros: any = { id_gasto: null, tipo_gasto: 'Otro', monto: 0 };

  public loading = false;
  public saving = false;
  public opAvanzadas = false;
  public distribucionManual: boolean = false;
  public gastosTransporte: any[] = [];
  public gastosSeguro: any[] = [];
  public gastosOtros: any[] = [];
  public bodegas: any = [];

  public selectedGastosTransporte: number[] = [];
  public selectedGastosSeguro: number[] = [];
  public selectedGastosOtros: number[] = [];

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit() {
    this.inicializarRetaceo();
    this.cargarFiltros();
    this.cargarBodegas();

    // Si estamos editando un retaceo existente
    if (this.route.snapshot.paramMap.get('id')) {
      this.cargarRetaceoExistente(+this.route.snapshot.paramMap.get('id')!);
    }
  }

  cargarBodegas() {
    this.loading = true;
    this.apiService.getAll('bodegas/list').subscribe(
      (bodegas) => {
        this.bodegas = bodegas;
        this.loading = false;
        
        // Una vez cargadas las bodegas, cargamos los datos para la bodega seleccionada
        this.cargarDatosPorBodega();
      }, 
      (error) => { 
        this.alertService.error(error); 
        this.loading = false;
      }
    );
  }

  // Nueva función para manejar el cambio de bodega
  onBodegaChange() {
    this.compras = [];
    this.gastos = [];
    this.distribucion = [];
    this.retaceo.id_compra = null;
    
    // Actualizar filtros con la nueva bodega seleccionada
    this.filtros.id_sucursal = this.retaceo.id_sucursal;
    
    // Cargar datos específicos para esta bodega
    this.cargarDatosPorBodega();
  }

  cargarDatosPorBodega() {
    if (!this.retaceo.id_sucursal) return;
    
    this.loading = true;
    this.filtros.id_sucursal = this.retaceo.id_sucursal;
    
    // Cargar compras filtradas por bodega
    this.apiService.getAll('compras', this.filtros).subscribe(
      (compras) => {
        this.compras = compras.data.filter(
          (c: any) => c.estado === 'Pagada' || c.estado === 'Pendiente'
        );
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );

    // Cargar gastos filtrados por bodega
    this.apiService.getAll('gastos', this.filtros).subscribe(
      (gastos) => {
        this.gastos = gastos.data;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  obtenerConceptoGasto(id_gasto: number): string {
    const gasto = this.gastos.find((g: any) => g.id === id_gasto);
    return gasto ? gasto.concepto : 'Gasto no encontrado';
  }

  actualizarEstado(nuevoEstado: string) {
    this.loading = true;

    const datosActualizacion = {
      id: this.retaceo.id,
      estado: nuevoEstado,
    };

    this.apiService.store('retaceo/estado', datosActualizacion).subscribe(
      (response) => {
        this.retaceo.estado = nuevoEstado;

        let mensaje = '';
        if (nuevoEstado === 'Aplicado') {
          mensaje = 'El retaceo ha sido aplicado y los costos actualizados';
        } else if (nuevoEstado === 'Anulado') {
          mensaje = 'El retaceo ha sido anulado';
        }

        this.alertService.success(mensaje, 'Cambio de estado');
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  obtenerNombreColumnaIncoterm(): string {
    switch (this.retaceo.incoterm) {
      case 'FOB':
        return 'FOB';
      case 'CIF':
        return 'CIF';
      case 'EXW':
        return 'EXW';
      case 'FCA':
        return 'FCA';
      case 'FAS':
        return 'FAS';
      case 'CFR':
        return 'CFR';
      case 'CPT':
        return 'CPT';
      case 'CIP':
        return 'CIP';
      case 'DAT':
        return 'DAT';
      case 'DAP':
        return 'DAP';
      case 'DDP':
        return 'DDP';
      default:
        return 'FOB'; // Valor predeterminado
    }
  }
  
  procesarGastosDelRetaceo(gastos: any[]) {
    if (!gastos || gastos.length === 0) {
      return;
    }
  
    // Limpiar los arrays
    this.gastosTransporte = [];
    this.gastosSeguro = [];
    this.gastosOtros = [];
    this.selectedGastosTransporte = [];
    this.selectedGastosSeguro = [];
    this.selectedGastosOtros = [];
  
    // Agrupar gastos por tipo
    gastos.forEach((gasto: any) => {
      const gastoObj = {
        id: gasto.id,
        id_retaceo: gasto.id_retaceo,
        id_gasto: gasto.id_gasto,
        tipo_gasto: gasto.tipo_gasto,
        monto: parseFloat(gasto.monto || 0)
      };
  
      switch (gasto.tipo_gasto) {
        case 'Transporte':
          this.gastosTransporte.push(gastoObj);
          this.selectedGastosTransporte.push(gasto.id_gasto);
          break;
        case 'Seguro':
          this.gastosSeguro.push(gastoObj);
          this.selectedGastosSeguro.push(gasto.id_gasto);
          break;
        case 'Otro':
          this.gastosOtros.push(gastoObj);
          this.selectedGastosOtros.push(gasto.id_gasto);
          break;
      }
    });
  }
  
  procesarDistribucionDelRetaceo(distribucion: any[]) {
    if (!distribucion || distribucion.length === 0) {
      return;
    }
  
    this.distribucion = distribucion;
  
    this.distribucion.forEach((item: any) => {
      item.cantidad = parseFloat(item.cantidad || 0);
      item.costo_original = parseFloat(item.costo_original || 0);
      item.valor_fob = parseFloat(item.valor_fob || 0);
      item.porcentaje_distribucion = parseFloat(item.porcentaje_distribucion || 0);
      item.porcentaje_dai = parseFloat(item.porcentaje_dai || 0);
      
      item.monto_transporte = parseFloat(item.monto_transporte || 0);
      item.monto_seguro = parseFloat(item.monto_seguro || 0);
      item.monto_dai = parseFloat(item.monto_dai || 0);
      item.monto_otros = parseFloat(item.monto_otros || 0);
      
      item.costo_landed = parseFloat(item.costo_landed || 0);
      item.costo_retaceado = parseFloat(item.costo_retaceado || 0);
  
      if (item.id_producto) {
        this.apiService.read('producto/', item.id_producto).subscribe(
          (producto) => {
            item.producto = producto;
          },
          (error) => {
            console.error(`Error al cargar producto ID ${item.id_producto}:`, error);
          }
        );
      }
    });
  }GastosSeleccionados(tipo: string) {
    switch (tipo) {
      case 'Transporte':
        // Eliminar gastos que ya no están seleccionados
        this.gastosTransporte = this.gastosTransporte.filter(gasto => 
          this.selectedGastosTransporte.includes(gasto.id_gasto)
        );
        
        // Agregar nuevos gastos seleccionados
        this.selectedGastosTransporte.forEach(id_gasto => {
          // Verificar si ya existe
          const existe = this.gastosTransporte.some(g => g.id_gasto === id_gasto);
          if (!existe) {
            const gastoInfo = this.gastos.find((g: any) => g.id === id_gasto);
            this.gastosTransporte.push({
              id_gasto: id_gasto,
              tipo_gasto: 'Transporte',
              monto: gastoInfo ? gastoInfo.total : 0
            });
          }
        });
        break;
        
      case 'Seguro':
        // Lógica similar para seguro
        this.gastosSeguro = this.gastosSeguro.filter(gasto => 
          this.selectedGastosSeguro.includes(gasto.id_gasto)
        );
        
        this.selectedGastosSeguro.forEach(id_gasto => {
          const existe = this.gastosSeguro.some(g => g.id_gasto === id_gasto);
          if (!existe) {
            const gastoInfo = this.gastos.find((g: any) => g.id === id_gasto);
            this.gastosSeguro.push({
              id_gasto: id_gasto,
              tipo_gasto: 'Seguro',
              monto: gastoInfo ? gastoInfo.total : 0
            });
          }
        });
        break;
        
      case 'Otro':
        // Lógica similar para otros gastos
        this.gastosOtros = this.gastosOtros.filter(gasto => 
          this.selectedGastosOtros.includes(gasto.id_gasto)
        );
        
        this.selectedGastosOtros.forEach(id_gasto => {
          const existe = this.gastosOtros.some(g => g.id_gasto === id_gasto);
          if (!existe) {
            const gastoInfo = this.gastos.find((g: any) => g.id === id_gasto);
            this.gastosOtros.push({
              id_gasto: id_gasto,
              tipo_gasto: 'Otro',
              monto: gastoInfo ? gastoInfo.total : 0
            });
          }
        });
        break;
    }
    
    this.calcularTotalGastos();
  }

  cargarRetaceoExistente(id: number) {
    this.loading = true;
    this.apiService.read('retaceo/', id).subscribe(
      (retaceo) => {
        this.retaceo = retaceo;
        
        // Cargar gastos y distribución directamente del retaceo
        this.procesarGastosDelRetaceo(retaceo.gastos);
        this.procesarDistribucionDelRetaceo(retaceo.distribucion);
        
        this.calcularTotalGastos();
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  cargarFiltros() {
    this.filtros.id_sucursal = '';
    this.filtros.id_proveedor = '';
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
      id_sucursal: this.apiService.auth_user().id_bodega,
      id_usuario: this.apiService.auth_user().id,
      total_gastos: 0,
      total_retaceado: 0,
      incoterm: 'FOB',
      tasa_dai: 0,
      estado: 'Pendiente',
    };

    this.gastoTransporte = {
      id_gasto: null,
      tipo_gasto: 'Transporte',
      monto: 0,
    };
    this.gastoSeguro = { id_gasto: null, tipo_gasto: 'Seguro', monto: 0 };
    this.gastoDAI = { id_gasto: null, tipo_gasto: 'DAI', monto: 0 };
    this.gastoOtros = { id_gasto: null, tipo_gasto: 'Otro', monto: 0 };

    this.distribucion = [];
  }

  cargarDetallesCompra() {
    if (!this.retaceo.id_compra) return;

    this.loading = true;
    this.apiService.read('compra/', this.retaceo.id_compra).subscribe(
      (compra) => {
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
            porcentaje_dai: 0, // Nuevo campo
            monto_transporte: 0,
            monto_seguro: 0,
            monto_dai: 0,
            monto_otros: 0,
            costo_landed: 0,
            costo_retaceado: detalle.costo || 0,
          });
        });

        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  cargarGastosRetaceo() {
    if (!this.retaceo.id) return;

    this.apiService
      .getAll('retaceo_gastos', { id_retaceo: this.retaceo.id })
      .subscribe(
        (gastos) => {
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
        },
        (error) => {
          this.alertService.error(error);
        }
      );

    // Cargar distribución
    this.apiService
      .getAll('retaceo_distribucion', { id_retaceo: this.retaceo.id })
      .subscribe(
        (distribucion) => {
          this.distribucion = distribucion;
          // Obtener productos
          this.distribucion.forEach((item: any) => {
            this.apiService
              .read('producto/', item.id_producto)
              .subscribe((producto) => {
                item.producto = producto;
              });
          });
        },
        (error) => {
          this.alertService.error(error);
        }
      );
  }

  setGastoMonto(tipo: string) {
    let gasto;
    switch (tipo) {
      case 'Transporte':
        gasto = this.gastos.find(
          (g: any) => g.id === this.gastoTransporte.id_gasto
        );
        if (gasto) this.gastoTransporte.monto = gasto.total || 0;
        break;
      case 'Seguro':
        gasto = this.gastos.find(
          (g: any) => g.id === this.gastoSeguro.id_gasto
        );
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
    const totalTransporte = this.gastosTransporte.reduce((sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
    const totalSeguro = this.gastosSeguro.reduce((sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
    const totalOtros = this.gastosOtros.reduce((sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
    
    this.retaceo.total_gastos = (totalTransporte + totalSeguro + totalOtros).toFixed(2);
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
  
    const valorFobTotal = this.distribucion.reduce(
      (sum: number, item: any) => sum + parseFloat(item.valor_fob || 0),
      0
    );
  
    if (valorFobTotal <= 0) {
      this.alertService.error('El valor FOB total debe ser mayor que cero');
      return;
    }
  
    // Calcular los porcentajes de distribución (modo automático o manual)
    if (!this.distribucionManual) {
      this.distribucion.forEach((item: any) => {
        item.porcentaje_distribucion = (
          (parseFloat(item.valor_fob) / valorFobTotal) *
          100
        ).toFixed(2);
      });
    }
  
    // IMPORTANTE: Obtener el total de cada tipo de gasto
    const totalTransporte = this.gastosTransporte.reduce(
      (sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
    const totalSeguro = this.gastosSeguro.reduce(
      (sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
    const totalOtros = this.gastosOtros.reduce(
      (sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
  
    // Distribuir los gastos según el porcentaje
    this.distribucion.forEach((item: any) => {
      // Asegurar que el porcentaje de distribución sea un número
      const porcentaje = parseFloat(item.porcentaje_distribucion);
      
      // Calcular montos de gastos para este producto
      item.monto_transporte = ((porcentaje / 100) * totalTransporte).toFixed(2);
      item.monto_seguro = ((porcentaje / 100) * totalSeguro).toFixed(2);
      item.monto_dai = ((parseFloat(item.valor_fob) * parseFloat(item.porcentaje_dai || 0)) / 100).toFixed(2);
      item.monto_otros = ((porcentaje / 100) * totalOtros).toFixed(2);
  
      // Calcular landed cost y costo retaceado
      this.actualizarCostosProducto(item);
    });
  
    this.recalcularTotalRetaceado();
    this.alertService.success('Distribución calculada correctamente', 'Distribución');
  }

  onSubmit() {
    this.saving = true;

    const gastos = [
      ...this.gastosTransporte,
      ...this.gastosSeguro,
      ...this.gastosOtros
    ].filter(gasto => gasto.id_gasto && parseFloat(gasto.monto) > 0);

    // Preparar objeto para enviar al servidor
    const datosRetaceo = {
      ...this.retaceo,
      gastos: gastos,
      distribucion: this.distribucion,
    };

    this.apiService.store('retaceo', datosRetaceo).subscribe(
      (response) => {
        this.alertService.success('Retaceo aplicado correctamente', 'Retaceo');
        this.router.navigate(['/retaceos']);
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  toggleDiv() {
    this.opAvanzadas = !this.opAvanzadas;
  }
  calcularImpactoTotal(): number {
    if (!this.distribucion || this.distribucion.length === 0) {
      return 0;
    }

    let impactoTotal = 0;

    this.distribucion.forEach((item: any) => {
      const costoOriginal = parseFloat(item.costo_original);
      const costoRetaceado = parseFloat(item.costo_retaceado);
      const cantidad = parseFloat(item.cantidad);

      impactoTotal += (costoRetaceado - costoOriginal) * cantidad;
    });

    return impactoTotal;
  }


  recalcularDistribucion() {
    if (!this.distribucion || this.distribucion.length === 0) {
      return;
    }
  
    // Verificar que la suma de porcentajes sea 100%
    const totalPorcentaje = this.distribucion.reduce(
      (sum: number, item: any) =>
        sum + parseFloat(item.porcentaje_distribucion || 0),
      0
    );
  
    // Mostrar advertencia si los porcentajes no suman 100%
    if (Math.abs(totalPorcentaje - 100) > 0.01) {
      this.alertService.warning(
        `La suma de porcentajes (${totalPorcentaje.toFixed(
          2
        )}%) debe ser 100%.`,
        'Distribución'
      );
  
      // Si no estamos en modo manual, normalizar
      if (!this.distribucionManual) {
        // Normalizar porcentajes para que sumen 100%
        this.distribucion.forEach((item: any) => {
          item.porcentaje_distribucion = (
            (parseFloat(item.porcentaje_distribucion) / totalPorcentaje) *
            100
          ).toFixed(2);
        });
      }
    }
  
    // IMPORTANTE: Calcular el total de cada tipo de gasto
    const totalTransporte = this.gastosTransporte.reduce(
      (sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
    const totalSeguro = this.gastosSeguro.reduce(
      (sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
    const totalOtros = this.gastosOtros.reduce(
      (sum, gasto) => sum + parseFloat(gasto.monto || 0), 0);
  
    // Calcular montos basados en los porcentajes actuales
    this.distribucion.forEach((item: any) => {
      const porcentaje = parseFloat(item.porcentaje_distribucion || 0);
      
      // Distribuir gastos según porcentaje
      item.monto_transporte = ((porcentaje / 100) * totalTransporte).toFixed(2);
      item.monto_seguro = ((porcentaje / 100) * totalSeguro).toFixed(2);
      item.monto_otros = ((porcentaje / 100) * totalOtros).toFixed(2);
      
      // Calcular el DAI según el porcentaje del producto
      if (item.porcentaje_dai) {
        item.monto_dai = ((parseFloat(item.valor_fob) * parseFloat(item.porcentaje_dai)) / 100).toFixed(2);
      }
  
      this.actualizarCostosProducto(item);
    });
  
    this.recalcularTotalRetaceado();
  }

  guardarRetaceo() {
    const totalPorcentaje = this.distribucion.reduce(
      (sum: number, item: any) =>
        sum + parseFloat(item.porcentaje_distribucion || 0),
      0
    );

    if (Math.abs(totalPorcentaje - 100) > 0.01) {
      this.alertService.error(
        `La suma de porcentajes de distribución (${totalPorcentaje.toFixed(
          2
        )}%) debe ser exactamente 100% antes de guardar.`
      );
      return;
    }
    if (!this.distribucion || this.distribucion.length === 0) {
      this.alertService.error('No hay productos para aplicar el retaceo');
      return;
    }

    if (parseFloat(this.retaceo.total_gastos) <= 0) {
      this.alertService.warning('No hay gastos para distribuir', 'Retaceo');
      return;
    }

    const faltaDAI = this.distribucion.some(
      (item: any) =>
        item.porcentaje_dai === null ||
        item.porcentaje_dai === undefined ||
        parseFloat(item.porcentaje_dai) < 0
    );

    if (faltaDAI) {
      this.alertService.warning(
        'Hay productos sin porcentaje de DAI asignado',
        'Retaceo'
      );
      return;
    }

    const impactoTotal = this.calcularImpactoTotal();

    // Mostrar confirmación con detalles
    Swal.fire({
      title: 'Confirmar Retaceo',
      html: `
      <div class="text-start">
        <p>Esta acción actualizará los costos de <strong>${
          this.distribucion.length
        }</strong> productos en inventario.</p>
        <ul>
          <li>Total de gastos a distribuir: <strong>${
            this.retaceo.total_gastos
          }</strong></li>
          <li>Impacto en el valor del inventario: <strong class="${
            impactoTotal > 0 ? 'text-success' : 'text-danger'
          }">${impactoTotal.toFixed(2)}</strong></li>
        </ul>
        <p class="mt-3 fw-bold">¿Confirma aplicar estos cambios?</p>
        <p class="text-muted small">Esta acción modificará permanentemente los costos de los productos en el inventario.</p>
      </div>
    `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, aplicar retaceo',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
    }).then((result) => {
      if (result.isConfirmed) {
        this.onSubmit();
      }
    });
  }

  calcularDAIProducto(item: any) {
    item.monto_dai = (
      (parseFloat(item.valor_fob) * parseFloat(item.porcentaje_dai)) /
      100
    ).toFixed(2);

    this.actualizarCostosProducto(item);

    // Actualizar el total retaceado
    this.recalcularTotalRetaceado();
  }

  actualizarCostosProducto(item: any) {
    item.costo_landed = (
      parseFloat(item.valor_fob) +
      parseFloat(item.monto_transporte) +
      parseFloat(item.monto_seguro) +
      parseFloat(item.monto_dai) +
      parseFloat(item.monto_otros)
    ).toFixed(2);

    item.costo_retaceado = (item.costo_landed / item.cantidad).toFixed(2);
  }

  recalcularTotalRetaceado() {
    this.retaceo.total_retaceado = this.distribucion
      .reduce(
        (sum: number, item: any) => sum + parseFloat(item.costo_landed || 0),
        0
      )
      .toFixed(2);
  }

  // Métodos para calcular los totales
  calcularTotalCantidad(): number {
    return this.distribucion.reduce(
      (total: number, item: any) => total + parseFloat(item.cantidad || 0),
      0
    );
  }

  calcularTotalCostoAntes(): number {
    return this.distribucion.reduce(
      (total: number, item: any) =>
        total +
        parseFloat(item.costo_original || 0) * parseFloat(item.cantidad || 0),
      0
    );
  }

  calcularTotalFOB(): number {
    return this.distribucion.reduce(
      (total: number, item: any) => total + parseFloat(item.valor_fob || 0),
      0
    );
  }

  calcularTotalTransporte(): number {
    return this.distribucion.reduce(
      (total: number, item: any) =>
        total + parseFloat(item.monto_transporte || 0),
      0
    );
  }

  calcularTotalSeguro(): number {
    return this.distribucion.reduce(
      (total: number, item: any) => total + parseFloat(item.monto_seguro || 0),
      0
    );
  }

  calcularTotalDAI(): number {
    return this.distribucion.reduce(
      (total: number, item: any) => total + parseFloat(item.monto_dai || 0),
      0
    );
  }

  calcularTotalOtros(): number {
    return this.distribucion.reduce(
      (total: number, item: any) => total + parseFloat(item.monto_otros || 0),
      0
    );
  }

  calcularTotalLanded(): number {
    return this.distribucion.reduce(
      (total: number, item: any) => total + parseFloat(item.costo_landed || 0),
      0
    );
  }
  
  calcularTotalRetaceado(): number {
    return this.distribucion.reduce(
      (total: number, item: any) =>
        total + parseFloat(item.costo_retaceado || 0),
      0
    );
  }

  cambiarEstado(nuevoEstado: string) {
    // Verificar el estado actual y si el cambio es válido
    if (this.retaceo.estado === 'Aplicado' && nuevoEstado !== 'Anulado') {
      this.alertService.warning(
        'Un retaceo aplicado solo puede anularse',
        'Cambio de estado'
      );
      return;
    }

    if (this.retaceo.estado === 'Anulado') {
      this.alertService.warning(
        'Un retaceo anulado no puede cambiar de estado',
        'Cambio de estado'
      );
      return;
    }

    // Confirmar el cambio de estado
    let mensaje = '';
    let confirmacionNecesaria = true;

    if (nuevoEstado === 'Aplicado') {
      mensaje =
        'Esta acción aplicará los nuevos costos a los productos en inventario. ¿Desea continuar?';
    } else if (nuevoEstado === 'Anulado') {
      mensaje =
        'Esta acción anulará el retaceo y los costos no serán aplicados. ¿Desea continuar?';
    }

    if (confirmacionNecesaria) {
      Swal.fire({
        title: 'Cambiar estado',
        text: mensaje,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar',
      }).then((result) => {
        if (result.isConfirmed) {
          this.actualizarEstado(nuevoEstado);
        }
      });
    } else {
      this.actualizarEstado(nuevoEstado);
    }
  }


actualizarGastosSeleccionados(tipo: string) {
  switch (tipo) {
    case 'Transporte':
      this.gastosTransporte = this.gastosTransporte.filter(gasto => 
        this.selectedGastosTransporte.includes(gasto.id_gasto)
      );
      
      this.selectedGastosTransporte.forEach(id_gasto => {
        // Verificar si ya existe
        const existe = this.gastosTransporte.some(g => g.id_gasto === id_gasto);
        if (!existe) {
          const gastoInfo = this.gastos.find((g: any) => g.id === id_gasto);
          this.gastosTransporte.push({
            id_gasto: id_gasto,
            tipo_gasto: 'Transporte',
            monto: gastoInfo ? gastoInfo.total : 0
          });
        }
      });
      break;
      
    case 'Seguro':
      // Lógica similar para seguro
      this.gastosSeguro = this.gastosSeguro.filter(gasto => 
        this.selectedGastosSeguro.includes(gasto.id_gasto)
      );
      
      this.selectedGastosSeguro.forEach(id_gasto => {
        const existe = this.gastosSeguro.some(g => g.id_gasto === id_gasto);
        if (!existe) {
          const gastoInfo = this.gastos.find((g: any) => g.id === id_gasto);
          this.gastosSeguro.push({
            id_gasto: id_gasto,
            tipo_gasto: 'Seguro',
            monto: gastoInfo ? gastoInfo.total : 0
          });
        }
      });
      break;
      
    case 'Otro':
      // Lógica similar para otros gastos
      this.gastosOtros = this.gastosOtros.filter(gasto => 
        this.selectedGastosOtros.includes(gasto.id_gasto)
      );
      
      this.selectedGastosOtros.forEach(id_gasto => {
        const existe = this.gastosOtros.some(g => g.id_gasto === id_gasto);
        if (!existe) {
          const gastoInfo = this.gastos.find((g: any) => g.id === id_gasto);
          this.gastosOtros.push({
            id_gasto: id_gasto,
            tipo_gasto: 'Otro',
            monto: gastoInfo ? gastoInfo.total : 0
          });
        }
      });
      break;
  }
  
  this.calcularTotalGastos();
}
}
