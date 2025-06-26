import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';


interface Gasto {
  id?: number;
  id_retaceo?: number;
  id_gasto: number;
  tipo_gasto: 'Transporte' | 'Seguro' | 'DAI' | 'Otro';
  monto: number;
}

interface ItemDistribucion {
  id_retaceo?: number;
  id_producto: number;
  id_detalle_compra: number;
  producto?: any;
  cantidad: number;
  costo_original: number;
  valor_fob: number;
  porcentaje_distribucion: number;
  porcentaje_dai: number;
  monto_transporte: number;
  monto_seguro: number;
  monto_dai: number;
  monto_otros: number;
  costo_landed: number;
  costo_retaceado: number;
}

@Component({
  selector: 'app-retaceo',
  templateUrl: './retaceo.component.html',
  styleUrls: ['./retaceo.component.css'],
})
export class RetaceoComponent implements OnInit {
  public retaceo: any = {};
  public compras: any[] = [];
  public gastos: any[] = [];
  public distribucion: ItemDistribucion[] = [];
  public detallesCompra: any[] = [];
  public filtros: any = {};

  public loading = false;
  public saving = false;
  public opAvanzadas = false;
  public distribucionManual = false;
  public gastosMap: {
    [tipo: string]: {
      lista: Gasto[];
      seleccionados: number[];
    }
  } = {
    'Transporte': { lista: [], seleccionados: [] },
    'Seguro': { lista: [], seleccionados: [] },
    'Otro': { lista: [], seleccionados: [] }
  };

  // Propiedades para mantener la compatibilidad con el HTML existente
  public get gastosTransporte(): Gasto[] { return this.gastosMap['Transporte'].lista; }
  public get gastosSeguro(): Gasto[] { return this.gastosMap['Seguro'].lista; }
  public get gastosOtros(): Gasto[] { return this.gastosMap['Otro'].lista; }

  public get selectedGastosTransporte(): number[] { return this.gastosMap['Transporte'].seleccionados; }
  public set selectedGastosTransporte(value: number[]) { this.gastosMap['Transporte'].seleccionados = value; }

  public get selectedGastosSeguro(): number[] { return this.gastosMap['Seguro'].seleccionados; }
  public set selectedGastosSeguro(value: number[]) { this.gastosMap['Seguro'].seleccionados = value; }

  public get selectedGastosOtros(): number[] { return this.gastosMap['Otro'].seleccionados; }
  public set selectedGastosOtros(value: number[]) { this.gastosMap['Otro'].seleccionados = value; }

  public bodegas: any[] = [];

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

        this.cargarDatosPorBodega();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  onBodegaChange() {
    this.compras = [];
    this.gastos = [];
    this.distribucion = [];
    this.retaceo.id_compra = null;


    Object.keys(this.gastosMap).forEach(tipo => {
      this.gastosMap[tipo].lista = [];
      this.gastosMap[tipo].seleccionados = [];
    });

    // Actualizar filtros con la nueva bodega seleccionada
    this.filtros.id_bodega = this.retaceo.id_bodega;

    // Cargar datos específicos para esta bodega
    this.cargarDatosPorBodega();
  }

  cargarDatosPorBodega() {
    if (!this.retaceo.id_bodega) return;

    this.loading = true;
    this.filtros.id_bodega = this.retaceo.id_bodega;
    if (this.route.snapshot.paramMap.get('id')) {
      this.filtros.es_retaceo =false;
    }
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
        // this.gastos = gastos.data.filter(
        //   (c: any) => c.estado === 'Confirmado'
        // );
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

  // Método optimizado para actualizar los gastos seleccionados
  actualizarGastosSeleccionados(tipo: string) {
    if (!this.gastosMap[tipo]) {
      console.error(`Tipo de gasto no válido: ${tipo}`);
      return;
    }

    const gastoInfo = this.gastosMap[tipo];

    // Filtrar gastos que ya no están seleccionados
    gastoInfo.lista = gastoInfo.lista.filter(gasto =>
      gastoInfo.seleccionados.includes(gasto.id_gasto)
    );

    // Agregar nuevos gastos seleccionados
    gastoInfo.seleccionados.forEach(id_gasto => {
      // Verificar si ya existe
      const existe = gastoInfo.lista.some(g => g.id_gasto === id_gasto);
      if (!existe) {
        const gastoDetalle = this.gastos.find((g: any) => g.id === id_gasto);
        gastoInfo.lista.push({
          id_gasto: id_gasto,
          tipo_gasto: tipo as any,
          monto: gastoDetalle ? gastoDetalle.sub_total : 0
        });
      }
    });

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
    this.filtros = {
      id_bodega: '',
      id_sucursal: '',
      id_proveedor: '',
      id_usuario: '',
      id_canal: '',
      id_documento: '',
      id_proyecto: '',
      forma_pago: '',
      dte: '',
      estado: '',
      buscador: '',
      orden: 'fecha',
      direccion: 'desc',
      paginate: 10,
      es_retaceo: true,
    };
  }

  inicializarRetaceo() {
    this.retaceo = {
      fecha: this.apiService.date(),
      id_empresa: this.apiService.auth_user().id_empresa,
      id_bodega: this.apiService.auth_user().id_bodega,
      id_sucursal: this.apiService.auth_user().id_sucursal,
      id_usuario: this.apiService.auth_user().id,
      total_gastos: 0,
      total_retaceado: 0,
      incoterm: 'FOB',
      tasa_dai: 0,
      estado: 'Pendiente',
    };

    // Limpiar todos los gastos
    Object.keys(this.gastosMap).forEach(tipo => {
      this.gastosMap[tipo].lista = [];
      this.gastosMap[tipo].seleccionados = [];
    });

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
            id_retaceo: 0,
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

  calcularTotalGastos() {
    // Calcular el total por cada tipo de gasto
    const totales = Object.keys(this.gastosMap).reduce((sum, tipo) => {
      const totalTipo = this.gastosMap[tipo].lista.reduce(
        (acc, gasto) => acc + parseFloat(gasto.monto?.toString() || '0'), 0);

      return sum + totalTipo;
    }, 0);

    this.retaceo.total_gastos = totales.toFixed(2);
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
      (sum, item) => sum + parseFloat(item.valor_fob?.toString() || '0'),
      0
    );

    if (valorFobTotal <= 0) {
      this.alertService.error('El valor FOB total debe ser mayor que cero');
      return;
    }

    // Calcular los porcentajes de distribución (modo automático o manual)
    if (!this.distribucionManual) {
      this.distribucion.forEach((item) => {
        // Limitar a 3 decimales el porcentaje de distribución
        item.porcentaje_distribucion = Number(
          ((parseFloat(item.valor_fob?.toString() || '0') / valorFobTotal) * 100).toFixed(3)
        );
      });
    }

    // Obtener total de cada tipo de gasto
    const totalesPorTipo = {
      'Transporte': this.calcularTotalPorTipoGasto('Transporte'),
      'Seguro': this.calcularTotalPorTipoGasto('Seguro'),
      'Otro': this.calcularTotalPorTipoGasto('Otro')
    };

    // Distribuir los gastos según el porcentaje
    this.distribucion.forEach((item) => {
      // Asegurar que el porcentaje de distribución sea un número
      const porcentaje = parseFloat(item.porcentaje_distribucion?.toString() || '0');

      // Calcular montos de gastos para este producto y redondear a 1 decimales
      item.monto_transporte = Number(((porcentaje / 100) * totalesPorTipo['Transporte']).toFixed(3));
      item.monto_seguro = Number(((porcentaje / 100) * totalesPorTipo['Seguro']).toFixed(3));
      item.monto_otros = Number(((porcentaje / 100) * totalesPorTipo['Otro']).toFixed(3));

      // Calcular la base para el DAI (suma de valor FOB + transporte + seguro)
      const baseDAI = parseFloat(item.valor_fob?.toString() || '0') +
                     parseFloat(item.monto_transporte?.toString() || '0') +
                     parseFloat(item.monto_seguro?.toString() || '0');

      // Calcular el DAI aplicando el porcentaje a la base
      item.monto_dai = Number(((baseDAI * parseFloat(item.porcentaje_dai?.toString() || '0')) / 100).toFixed(4));

      // Calcular landed cost y costo retaceado
      this.actualizarCostosProducto(item);
    });

    this.recalcularTotalRetaceado();
    this.alertService.success('Distribución calculada correctamente', 'Distribución');
  }

  // Método auxiliar para calcular el total por tipo de gasto
  calcularTotalPorTipoGasto(tipo: string): number {
    if (!this.gastosMap[tipo]) return 0;

    return this.gastosMap[tipo].lista.reduce(
      (acc, gasto) => acc + parseFloat(gasto.monto?.toString() || '0'), 0);
  }

  onSubmit() {
    this.saving = true;

    // Aplanar todos los gastos en un solo array
    const gastos = Object.keys(this.gastosMap).reduce((arr, tipo) => {
      const gastosValidos = this.gastosMap[tipo].lista.filter(
        gasto => gasto.id_gasto && parseFloat(gasto.monto?.toString() || '0') > 0
      );
      return [...arr, ...gastosValidos];
    }, [] as Gasto[]);

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

  calcularImpactoTotal(): number {
    if (!this.distribucion || this.distribucion.length === 0) {
      return 0;
    }

    let impactoTotal = 0;

    this.distribucion.forEach((item) => {
      const costoOriginal = parseFloat(item.costo_original?.toString() || '0');
      const costoRetaceado = parseFloat(item.costo_retaceado?.toString() || '0');
      const cantidad = parseFloat(item.cantidad?.toString() || '0');

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
      (sum, item) => sum + parseFloat(item.porcentaje_distribucion?.toString() || '0'),
      0
    );

    // Mostrar advertencia si los porcentajes no suman 100%
    if (Math.abs(totalPorcentaje - 100) > 0.01) {
      this.alertService.warning(
        `La suma de porcentajes (${totalPorcentaje.toFixed(2)}%) debe ser 100%.`,
        'Distribución'
      );

      // Si no estamos en modo manual, normalizar
      if (!this.distribucionManual) {
        // Normalizar porcentajes para que sumen 100%
        this.distribucion.forEach((item) => {
          const val = parseFloat(item.porcentaje_distribucion?.toString() || '0');
          // Redondear a 3 decimales máximo
          item.porcentaje_distribucion = Number((val / totalPorcentaje * 100).toFixed(3));
        });
      }
    }

    // Obtener total de cada tipo de gasto
    const totalesPorTipo = {
      'Transporte': this.calcularTotalPorTipoGasto('Transporte'),
      'Seguro': this.calcularTotalPorTipoGasto('Seguro'),
      'Otro': this.calcularTotalPorTipoGasto('Otro')
    };

    // Calcular montos basados en los porcentajes actuales
    this.distribucion.forEach((item) => {
      const porcentaje = parseFloat(item.porcentaje_distribucion?.toString() || '0');

      // Distribuir gastos según porcentaje y redondear a 2 decimales
      item.monto_transporte = Number(((porcentaje / 100) * totalesPorTipo['Transporte']).toFixed(2));
      item.monto_seguro = Number(((porcentaje / 100) * totalesPorTipo['Seguro']).toFixed(2));
      item.monto_otros = Number(((porcentaje / 100) * totalesPorTipo['Otro']).toFixed(2));

      // Calcular el DAI según el porcentaje del producto
      if (item.porcentaje_dai) {
        // Redondear a 4 decimales el porcentaje DAI
        item.porcentaje_dai = Number(parseFloat(item.porcentaje_dai.toString()).toFixed(4));

        // Calcular la base para el DAI (suma de valor FOB + transporte + seguro)
        const baseDAI = parseFloat(item.valor_fob?.toString() || '0') +
                       parseFloat(item.monto_transporte?.toString() || '0') +
                       parseFloat(item.monto_seguro?.toString() || '0');

        item.monto_dai = Number(((baseDAI * parseFloat(item.porcentaje_dai?.toString() || '0')) / 100).toFixed(4));
      }

      this.actualizarCostosProducto(item);
    });

    this.recalcularTotalRetaceado();
  }

  guardarRetaceo() {
    const totalPorcentaje = this.distribucion.reduce(
      (sum, item) => sum + parseFloat(item.porcentaje_distribucion?.toString() || '0'),
      0
    );

    if (Math.abs(totalPorcentaje - 100) > 0.01) {
      this.alertService.error(
        `La suma de porcentajes de distribución (${totalPorcentaje.toFixed(2)}%) debe ser exactamente 100% antes de guardar.`
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
      (item) =>
        item.porcentaje_dai === null ||
        item.porcentaje_dai === undefined ||
        parseFloat(item.porcentaje_dai?.toString() || '0') < 0
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

  calcularDAIProducto(item: ItemDistribucion) {
    // Calcular la base para el DAI (suma de valor FOB + transporte + seguro)
    const baseDAI = parseFloat(item.valor_fob?.toString() || '0') +
                   parseFloat(item.monto_transporte?.toString() || '0') +
                   parseFloat(item.monto_seguro?.toString() || '0');

    // Calcular el DAI aplicando el porcentaje a la base
    item.monto_dai = Number(((baseDAI * parseFloat(item.porcentaje_dai?.toString() || '0')) / 100).toFixed(4));

    this.actualizarCostosProducto(item);

    // Actualizar el total retaceado
    this.recalcularTotalRetaceado();
  }

  actualizarCostosProducto(item: ItemDistribucion) {
    item.costo_landed = (
      parseFloat(item.valor_fob?.toString() || '0') +
      parseFloat(item.monto_transporte?.toString() || '0') +
      parseFloat(item.monto_seguro?.toString() || '0') +
      parseFloat(item.monto_dai?.toString() || '0') +
      parseFloat(item.monto_otros?.toString() || '0')
    );

    item.costo_retaceado = parseFloat(item.cantidad?.toString() || '0') > 0 ?
      (item.costo_landed / parseFloat(item.cantidad?.toString() || '1')) : 0;
  }

  recalcularTotalRetaceado() {
    this.retaceo.total_retaceado = this.distribucion
      .reduce(
        (sum, item) => sum + parseFloat(item.costo_landed?.toString() || '0'),
        0
      )
      .toFixed(2);
  }


  calcularTotalCantidad(): number {
    return this.distribucion.reduce(
      (total, item) => total + parseFloat(item.cantidad?.toString() || '0'),
      0
    );
  }

  calcularTotalCostoAntes(): number {
    return this.distribucion.reduce(
      (total, item) =>
        total +
        parseFloat(item.costo_original?.toString() || '0') *
        parseFloat(item.cantidad?.toString() || '0'),
      0
    );
  }

  calcularTotalFOB(): number {
    return this.distribucion.reduce(
      (total, item) => total + parseFloat(item.valor_fob?.toString() || '0'),
      0
    );
  }

  calcularTotalTransporte(): number {
    return this.distribucion.reduce(
      (total, item) => total + parseFloat(item.monto_transporte?.toString() || '0'),
      0
    );
  }

  calcularTotalSeguro(): number {
    return this.distribucion.reduce(
      (total, item) => total + parseFloat(item.monto_seguro?.toString() || '0'),
      0
    );
  }

  calcularTotalDAI(): number {
    return this.distribucion.reduce(
      (total, item) => total + parseFloat(item.monto_dai?.toString() || '0'),
      0
    );
  }

  calcularTotalOtros(): number {
    return this.distribucion.reduce(
      (total, item) => total + parseFloat(item.monto_otros?.toString() || '0'),
      0
    );
  }

  calcularTotalLanded(): number {
    return this.distribucion.reduce(
      (total, item) => total + parseFloat(item.costo_landed?.toString() || '0'),
      0
    );
  }

  calcularTotalRetaceado(): number {
    return this.distribucion.reduce(
      (total, item) => total + parseFloat(item.costo_retaceado?.toString() || '0'),
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
    let mensaje = nuevoEstado === 'Aplicado'
      ? 'Esta acción aplicará los nuevos costos a los productos en inventario. ¿Desea continuar?'
      : 'Esta acción anulará el retaceo y los costos no serán aplicados. ¿Desea continuar?';

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

        let mensaje = nuevoEstado === 'Aplicado'
          ? 'El retaceo ha sido aplicado y los costos actualizados'
          : 'El retaceo ha sido anulado';

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
    const incoterms: { [key: string]: string } = {
      'FOB': 'FOB',
      'CIF': 'CIF',
      'EXW': 'EXW',
      'FCA': 'FCA',
      'FAS': 'FAS',
      'CFR': 'CFR',
      'CPT': 'CPT',
      'CIP': 'CIP',
      'DAT': 'DAT',
      'DAP': 'DAP',
      'DDP': 'DDP'
    };

    return incoterms[this.retaceo.incoterm] || 'FOB';
  }

  procesarGastosDelRetaceo(gastos: any[]) {
    if (!gastos || gastos.length === 0) {
      return;
    }

    // Limpiar todos los gastos
    Object.keys(this.gastosMap).forEach(tipo => {
      this.gastosMap[tipo].lista = [];
      this.gastosMap[tipo].seleccionados = [];
    });

    // Agrupar gastos por tipo
    gastos.forEach((gasto: any) => {
      const tipo = gasto.tipo_gasto;

      if (this.gastosMap[tipo]) {
        const gastoObj: Gasto = {
          id: gasto.id,
          id_retaceo: gasto.id_retaceo,
          id_gasto: gasto.id_gasto,
          tipo_gasto: tipo as any,
          monto: parseFloat(gasto.monto || 0)
        };

        this.gastosMap[tipo].lista.push(gastoObj);
        this.gastosMap[tipo].seleccionados.push(gasto.id_gasto);
      }
    });
  }

  procesarDistribucionDelRetaceo(distribucion: any[]) {
    if (!distribucion || distribucion.length === 0) {
      return;
    }

    this.distribucion = distribucion.map(item => ({
      ...item,
      cantidad: parseFloat(item.cantidad || 0),
      costo_original: parseFloat(item.costo_original || 0),
      valor_fob: parseFloat(item.valor_fob || 0),
      porcentaje_distribucion: parseFloat(item.porcentaje_distribucion || 0),
      porcentaje_dai: parseFloat(item.porcentaje_dai || 0),
      monto_transporte: parseFloat(item.monto_transporte || 0),
      monto_seguro: parseFloat(item.monto_seguro || 0),
      monto_dai: parseFloat(item.monto_dai || 0),
      monto_otros: parseFloat(item.monto_otros || 0),
      costo_landed: parseFloat(item.costo_landed || 0),
      costo_retaceado: parseFloat(item.costo_retaceado || 0),
    }));

    // Cargar los productos para cada ítem de la distribución
    this.distribucion.forEach((item) => {
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
  }
}
