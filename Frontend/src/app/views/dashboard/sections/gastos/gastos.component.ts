import { Component, EventEmitter, Input, OnInit, OnChanges, SimpleChanges, Output, ViewChild, ChangeDetectorRef } from '@angular/core';
import { ColDef, GridOptions, GridApi, ColumnApi } from 'ag-grid-community';
import { AgGridAngular } from 'ag-grid-angular';

@Component({
  selector: 'app-gastos',
  templateUrl: './gastos.component.html',
  styleUrls: ['./gastos.component.css']
})
export class GastosComponent implements OnInit, OnChanges {
  @Input() datos: any = {};
  @Output() filtrosCambiados = new EventEmitter<any>();

  // Datos originales (sin filtrar)
  datosOriginales: any = {};
  
  // Datos filtrados (se muestran en la vista)
  datosFiltrados: any = {};

  public inicializado: boolean = false;
  private filtrosListosParaEmitir = false;

  @ViewChild('detalleGastosGrid') detalleGastosGrid!: AgGridAngular;
  
  // AG Grid API
  private detalleGastosGridApi!: GridApi;
  
  // Quick filter text
  quickFilterTextGastos: string = '';
  
  // AG Grid options
  detalleGastosGridOptions: GridOptions = {};

  anio: string = new Date().getFullYear().toString();
  mes: string = '';
  filtroSucursal: string = '';
  filtroEstado: string = '';
  filtroCliente: string = '';
  mostrarFiltrosAdicionales: boolean = false;

  // Vista de métricas
  vistaMetricas: string = 'mes';

  // Filtros interactivos (se aplican localmente sin recargar)
  filtrosInteractivos: {
    proveedor?: string;
    categoria?: string;
    mes?: string;
  } = {};

  // Opciones para filtros
  sucursales: any[] = [];
  clientes: any[] = [];

  // Columnas para la tabla de detalle de gastos (AG Grid)
  detalleGastosColumnDefs: ColDef[] = [
    { 
      field: 'fecha', 
      headerName: 'Fecha', 
      width: 120,
      sortable: true,
      filter: true
    },
    { 
      field: 'proveedor', 
      headerName: 'Proveedor', 
      width: 180,
      sortable: true,
      filter: true
    },
    { 
      field: 'concepto', 
      headerName: 'Concepto', 
      width: 220,
      sortable: true,
      filter: true
    },
    { 
      field: 'documento', 
      headerName: 'Doc.', 
      width: 100,
      sortable: true,
      filter: true
    },
    { 
      field: 'correlativo', 
      headerName: 'Corr.', 
      width: 100,
      sortable: true,
      filter: true
    },
    { 
      field: 'gastosConIVA', 
      headerName: 'Gastos con IVA', 
      width: 150,
      sortable: true,
      filter: true,
      valueFormatter: (params: any) => {
        return params.value ? this.formatCurrency(params.value) : '';
      },
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    }
  ];

  constructor(private cdr: ChangeDetectorRef) { }

  ngOnInit(): void {
    // Configurar AG Grid
    this.configurarAGGrid();
    
    // Intentar inicializar si ya hay datos
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.inicializarDatos();
    }

    setTimeout(() => {
      this.filtrosListosParaEmitir = true;
    }, 100);
  }

  configurarAGGrid(): void {
    this.detalleGastosGridOptions = {
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true
      },
      enableCellTextSelection: true,
      ensureDomOrder: true,
      suppressExcelExport: false,
      suppressCsvExport: false,
      suppressHorizontalScroll: false,
      onGridReady: (params: any) => {
        this.detalleGastosGridApi = params.api;
      }
    };
  }

  onGridReadyGastos(params: any): void {
    this.detalleGastosGridApi = params.api;
    this.detalleGastosGridApi.sizeColumnsToFit();
  }

  onQuickFilterChangeGastos(): void {
    if (this.detalleGastosGridApi) {
      this.detalleGastosGridApi.setQuickFilter(this.quickFilterTextGastos);
    }
  }

  exportarCSVGastos(): void {
    if (this.detalleGastosGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.detalleGastosGridApi.exportDataAsCsv({
        fileName: `detalle-gastos-${fecha}.csv`
      });
    }
  }

  exportarExcelGastos(): void {
    if (this.detalleGastosGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.detalleGastosGridApi.exportDataAsCsv({
        fileName: `detalle-gastos-${fecha}.csv`
      });
    }
  }

  limpiarFiltrosGastos(): void {
    if (this.detalleGastosGridApi) {
      this.detalleGastosGridApi.setFilterModel(null);
      this.quickFilterTextGastos = '';
      this.detalleGastosGridApi.setQuickFilter('');
    }
  }

  ngOnChanges(changes: SimpleChanges): void {
    console.log('GastosComponent - ngOnChanges llamado', {
      hasChanges: !!changes['datos'],
      firstChange: changes['datos']?.firstChange,
      currentValue: changes['datos']?.currentValue ? Object.keys(changes['datos'].currentValue) : [],
      tieneDetalleGastos: !!(changes['datos']?.currentValue && changes['datos'].currentValue.detalleGastos)
    });
    
    if (changes['datos']) {
      const datosActuales = changes['datos'].currentValue;
      if (datosActuales && Object.keys(datosActuales).length > 0) {
        // Datos llegaron (ya sea en el primer cambio o después)
        this.inicializarDatos();
      }
    }
  }

  inicializarDatos(): void {
    console.log('GastosComponent - inicializarDatos llamado', {
      tieneDatos: !!this.datos,
      keysDatos: this.datos ? Object.keys(this.datos) : [],
      tieneDetalleGastos: !!(this.datos && this.datos.detalleGastos),
      detalleGastos: this.datos?.detalleGastos,
      datosCompletos: this.datos
    });
    
    if (this.datos && Object.keys(this.datos).length > 0) {
      // Verificar si detalleGastos existe, si no, intentar usar datos originales del servicio
      if (!this.datos.detalleGastos) {
        console.warn('GastosComponent - detalleGastos no existe en datos, verificando estructura completa');
        console.log('Estructura completa de datos:', JSON.stringify(this.datos, null, 2));
      }
      
      // Guardar datos originales
      this.datosOriginales = JSON.parse(JSON.stringify(this.datos));
      // Inicializar datos filtrados
      this.datosFiltrados = JSON.parse(JSON.stringify(this.datos));
      this.datos = this.datosFiltrados;
      this.inicializado = true;
      
      // Recalcular todos los gráficos con los datos iniciales
      if (this.datosFiltrados.detalleGastos) {
        this.recalcularMetricas();
        this.recalcularGastosPorMes();
        this.recalcularGastosVsPresupuesto();
        this.recalcularGastosVsAnioAnterior();
        this.recalcularGastosPorCategoria();
        this.recalcularGastosPorConcepto();
        this.recalcularGastosPorProveedor();
        this.recalcularGastosPorFormaPago();
      } else {
        console.error('GastosComponent - No se puede inicializar: falta detalleGastos');
      }
      
      this.cdr.detectChanges();
    } else {
      console.warn('GastosComponent - No hay datos para inicializar');
    }
  }

  cambiarVistaMetricas(vista: string): void {
    this.vistaMetricas = vista;
    // Recalcular los gráficos según la vista seleccionada
    if (vista === 'presupuesto') {
      this.recalcularGastosVsPresupuesto();
    } else if (vista === 'anio') {
      this.recalcularGastosVsAnioAnterior();
    } else {
      this.recalcularGastosPorMes();
    }
    this.cdr.detectChanges();
  }

  getTituloGraficoGastos(): string {
    switch (this.vistaMetricas) {
      case 'presupuesto':
        return 'Gastos totales vs presupuesto mensual';
      case 'anio':
        return 'Gastos totales año actual vs año anterior';
      default:
        return 'Gastos por mes';
    }
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-GT', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(value);
  }

  tieneFiltrosInteractivos(): boolean {
    return Object.keys(this.filtrosInteractivos).length > 0;
  }

  getFiltrosInteractivosTexto(): string {
    const filtros: string[] = [];
    if (this.filtrosInteractivos.proveedor) filtros.push(`Proveedor: ${this.filtrosInteractivos.proveedor}`);
    if (this.filtrosInteractivos.categoria) filtros.push(`Categoría: ${this.filtrosInteractivos.categoria}`);
    if (this.filtrosInteractivos.mes) filtros.push(`Mes: ${this.filtrosInteractivos.mes}`);
    return filtros.join(', ');
  }

  aplicarFiltros(): void {
    if (!this.filtrosListosParaEmitir) {
      return;
    }
    if (!this.anio) {
      this.anio = new Date().getFullYear().toString();
    }
    const filtros: any = {
      anio: this.anio,
      sucursal: this.filtroSucursal,
      cliente: this.filtroCliente
    };
    if (this.mes) {
      filtros.mes = this.mes;
    }
    if (this.filtroEstado) {
      filtros.estadoGasto = this.filtroEstado;
    }
    this.filtrosCambiados.emit(filtros);
  }

  limpiarFiltros(): void {
    this.anio = new Date().getFullYear().toString();
    this.mes = '';
    this.filtroSucursal = '';
    this.filtroEstado = '';
    this.filtroCliente = '';
    this.limpiarFiltrosInteractivos();
    this.aplicarFiltros();
  }

  limpiarFiltrosInteractivos(): void {
    this.filtrosInteractivos = {};
    // Restaurar datos originales
    if (Object.keys(this.datosOriginales).length > 0) {
      this.datosFiltrados = JSON.parse(JSON.stringify(this.datosOriginales));
      
      // Recalcular todos los gráficos con datos originales
      this.recalcularMetricas();
      this.recalcularGastosPorMes();
      this.recalcularGastosPorCategoria();
      this.recalcularGastosPorConcepto();
      this.recalcularGastosPorProveedor();
      this.recalcularGastosPorFormaPago();
      
      // Actualizar referencia - crear nuevo objeto para forzar detección de cambios
      this.datos = { ...this.datosFiltrados };
      this.cdr.detectChanges();
    }
  }

  toggleFiltrosAdicionales() {
    this.mostrarFiltrosAdicionales = !this.mostrarFiltrosAdicionales;
  }

  onMesClick(event: { name: string; value: any; index: number }): void {
    if (event.name) {
      // Toggle: si ya está filtrado por este mes, quitar el filtro
      if (this.filtrosInteractivos.mes === event.name) {
        delete this.filtrosInteractivos.mes;
      } else {
        this.filtrosInteractivos.mes = event.name;
      }
      this.aplicarFiltrosInteractivos();
    }
  }

  onCategoriaClick(event: { name: string; value: any; index: number }): void {
    if (event.name) {
      // Toggle: si ya está filtrado por esta categoría, quitar el filtro
      if (this.filtrosInteractivos.categoria === event.name) {
        delete this.filtrosInteractivos.categoria;
      } else {
        this.filtrosInteractivos.categoria = event.name;
      }
      this.aplicarFiltrosInteractivos();
    }
  }

  onConceptoClick(event: { name: string; value: any; index: number }): void {
    if (event.name) {
      // Toggle: si ya está filtrado por este concepto, quitar el filtro
      if (this.filtrosInteractivos.categoria === event.name) {
        delete this.filtrosInteractivos.categoria;
      } else {
        this.filtrosInteractivos.categoria = event.name;
      }
      this.aplicarFiltrosInteractivos();
    }
  }

  onFormaPagoClick(event: { name: string; value: any; index: number }): void {
    // Por ahora no hay filtro de forma de pago, pero se puede agregar
    console.log('Forma de pago seleccionada:', event);
  }

  onProveedorClick(event: { name: string; value: any; index: number }): void {
    if (event.name) {
      // Toggle: si ya está filtrado por este proveedor, quitar el filtro
      if (this.filtrosInteractivos.proveedor === event.name) {
        delete this.filtrosInteractivos.proveedor;
      } else {
        this.filtrosInteractivos.proveedor = event.name;
      }
      this.aplicarFiltrosInteractivos();
    }
  }

  aplicarFiltrosInteractivos(): void {
    if (!this.inicializado || !this.datosOriginales.detalleGastos) {
      return;
    }

    // Restaurar datos originales
    this.datosFiltrados = JSON.parse(JSON.stringify(this.datosOriginales));
    
    // Filtrar detalle de gastos
    let gastosFiltrados = [...this.datosOriginales.detalleGastos];

    // Aplicar filtros
    if (this.filtrosInteractivos.proveedor) {
      gastosFiltrados = gastosFiltrados.filter((g: any) => 
        g.proveedor === this.filtrosInteractivos.proveedor
      );
    }

    if (this.filtrosInteractivos.categoria) {
      gastosFiltrados = gastosFiltrados.filter((g: any) => 
        g.concepto?.toLowerCase().includes(this.filtrosInteractivos.categoria?.toLowerCase() || '')
      );
    }

    if (this.filtrosInteractivos.mes) {
      const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                     'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
      const mesIndex = meses.indexOf(this.filtrosInteractivos.mes);
      if (mesIndex !== -1) {
        gastosFiltrados = gastosFiltrados.filter((g: any) => {
          if (g.fecha) {
            const fecha = new Date(g.fecha);
            return fecha.getMonth() === mesIndex;
          }
          return false;
        });
      }
    }

    // Actualizar datos filtrados
    this.datosFiltrados.detalleGastos = gastosFiltrados;

    // Recalcular todos los gráficos y métricas
    this.recalcularMetricas();
    this.recalcularGastosPorMes();
    this.recalcularGastosPorCategoria();
    this.recalcularGastosPorConcepto();
    this.recalcularGastosPorProveedor();
    this.recalcularGastosPorFormaPago();

    // Actualizar referencia - crear nuevo objeto para forzar detección de cambios
    this.datos = { ...this.datosFiltrados };
    
    // Forzar detección de cambios
    this.cdr.detectChanges();
  }

  recalcularMetricas(): void {
    if (!this.datosFiltrados.detalleGastos) return;

    const gastos = this.datosFiltrados.detalleGastos;
    const gastosConIVA = gastos.reduce((sum: number, g: any) => sum + (g.gastosConIVA || 0), 0);
    const gastosSinIVA = gastosConIVA / 1.12; // Asumiendo IVA del 12%

    // Calcular gastos del mes actual
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();
    const gastosMesActual = gastos
      .filter((g: any) => {
        if (g.fecha) {
          const fecha = new Date(g.fecha);
          return fecha.getMonth() === mesActual && fecha.getFullYear() === añoActual;
        }
        return false;
      })
      .reduce((sum: number, g: any) => sum + (g.gastosConIVA || 0), 0);

    // Calcular gastos del mes anterior
    const mesAnterior = mesActual === 0 ? 11 : mesActual - 1;
    const añoAnterior = mesActual === 0 ? añoActual - 1 : añoActual;
    const gastosMesAnterior = this.datosOriginales.detalleGastos
      ?.filter((g: any) => {
        if (g.fecha) {
          const fecha = new Date(g.fecha);
          return fecha.getMonth() === mesAnterior && fecha.getFullYear() === añoAnterior;
        }
        return false;
      })
      .reduce((sum: number, g: any) => sum + (g.gastosConIVA || 0), 0) || 0;

    const variacion = gastosMesActual - gastosMesAnterior;
    const aumentoPorcentaje = gastosMesAnterior > 0 
      ? Math.round((variacion / gastosMesAnterior) * 100) 
      : 0;

    if (!this.datosFiltrados.metricasGastos) {
      this.datosFiltrados.metricasGastos = {};
    }
    this.datosFiltrados.metricasGastos.gastosConIVA = gastosConIVA;
    this.datosFiltrados.metricasGastos.gastosSinIVA = gastosMesActual;
    this.datosFiltrados.metricasGastos.gastosMesAnterior = gastosMesAnterior;
    this.datosFiltrados.metricasGastos.variacionGastos = variacion;
    this.datosFiltrados.metricasGastos.aumentoCostosPorcentaje = aumentoPorcentaje;
  }

  recalcularGastosPorMes(): void {
    if (!this.datosFiltrados.detalleGastos) return;

    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const gastosPorMes: { [key: string]: number } = {};
    
    // Inicializar todos los meses en 0
    meses.forEach(mes => {
      gastosPorMes[mes] = 0;
    });
    
    this.datosFiltrados.detalleGastos.forEach((g: any) => {
      if (g.fecha) {
        try {
          const fecha = new Date(g.fecha);
          if (!isNaN(fecha.getTime())) {
            const mesIndex = fecha.getMonth();
            const mesNombre = meses[mesIndex];
            gastosPorMes[mesNombre] = (gastosPorMes[mesNombre] || 0) + (g.gastosConIVA || 0);
          }
        } catch (e) {
          console.warn('Fecha inválida:', g.fecha);
        }
      }
    });

    const labels = meses;
    const data = labels.map(m => gastosPorMes[m] || 0);

    // Crear o actualizar la configuración del gráfico
    this.datosFiltrados.gastosPorMesConfig = {
      title: '',
      type: 'line',
      labels,
      data,
      colors: ['#F19447']
    };
  }

  recalcularGastosPorCategoria(): void {
    if (!this.datosFiltrados.detalleGastos) return;

    // Mapeo de conceptos a categorías (simplificado)
    const categoriaMap: { [key: string]: string } = {
      'compras': 'Compras',
      'materia prima': 'Compras',
      'planilla': 'G. operativos',
      'alquiler': 'G. operativos',
      'luz': 'G. operativos',
      'agua': 'G. operativos',
      'electricidad': 'G. operativos',
      'servicios': 'G. administrativos',
      'impuestos': 'G. financieros',
      'publicidad': 'G. comerciales',
      'costo': 'Costo de ventas'
    };

    const gastosPorCategoria: { [key: string]: number } = {};
    
    // Inicializar todas las categorías en 0
    const categorias = ['Compras', 'G. operativos', 'G. administrativos', 'G. financieros', 'G. comerciales', 'Costo de ventas'];
    categorias.forEach(cat => {
      gastosPorCategoria[cat] = 0;
    });
    
    this.datosFiltrados.detalleGastos.forEach((g: any) => {
      const concepto = (g.concepto || '').toLowerCase();
      let categoria = 'Gastos varios';
      
      for (const [key, value] of Object.entries(categoriaMap)) {
        if (concepto.includes(key)) {
          categoria = value;
          break;
        }
      }
      
      // Solo agregar si la categoría está en la lista
      if (categorias.includes(categoria)) {
        gastosPorCategoria[categoria] = (gastosPorCategoria[categoria] || 0) + (g.gastosConIVA || 0);
      }
    });

    const labels = categorias;
    const data = labels.map(c => gastosPorCategoria[c] || 0);

    // Crear o actualizar la configuración del gráfico
    this.datosFiltrados.gastosPorCategoriaConfig = {
      title: '',
      type: 'bar',
      labels,
      data,
      colors: ['#F19447'],
      horizontal: true
    };
  }

  recalcularGastosPorConcepto(): void {
    if (!this.datosFiltrados.detalleGastos) return;

    const gastosPorConcepto: { [key: string]: number } = {};
    
    this.datosFiltrados.detalleGastos.forEach((g: any) => {
      const concepto = g.concepto || 'Sin concepto';
      gastosPorConcepto[concepto] = (gastosPorConcepto[concepto] || 0) + (g.gastosConIVA || 0);
    });

    // Ordenar por monto y tomar los top 13
    const sorted = Object.entries(gastosPorConcepto)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 13);

    const labels = sorted.map(([name]) => name.length > 15 ? name.substring(0, 15) + '...' : name);
    const data = sorted.map(([, value]) => value);

    // Crear o actualizar la configuración del gráfico
    this.datosFiltrados.gastosPorConceptoConfig = {
      title: '',
      type: 'bar',
      labels,
      data,
      colors: ['#F19447'],
      rotateLabels: 45
    };
  }

  recalcularGastosPorProveedor(): void {
    if (!this.datosFiltrados.detalleGastos) return;

    const gastosPorProveedor: { [key: string]: number } = {};
    
    this.datosFiltrados.detalleGastos.forEach((g: any) => {
      const proveedor = g.proveedor || 'Sin proveedor';
      gastosPorProveedor[proveedor] = (gastosPorProveedor[proveedor] || 0) + (g.gastosConIVA || 0);
    });

    // Actualizar la lista de proveedores ordenada por monto
    this.datosFiltrados.gastosPorProveedor = Object.entries(gastosPorProveedor)
      .map(([name, amount]) => ({ name, amount: amount as number }))
      .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
  }

  recalcularGastosPorFormaPago(): void {
    // Por ahora mantener los datos originales del treemap si existen
    // En el futuro se puede implementar filtrado por forma de pago si se agrega ese campo a detalleGastos
    if (!this.datosFiltrados.gastosPorFormaPagoConfig && this.datosOriginales.gastosPorFormaPagoConfig) {
      this.datosFiltrados.gastosPorFormaPagoConfig = JSON.parse(JSON.stringify(this.datosOriginales.gastosPorFormaPagoConfig));
    }
  }

  recalcularGastosVsPresupuesto(): void {
    if (!this.datosFiltrados.detalleGastos) return;

    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const gastosPorMes: { [key: string]: number } = {};
    
    // Calcular gastos reales por mes
    this.datosFiltrados.detalleGastos.forEach((g: any) => {
      if (g.fecha) {
        try {
          const fecha = new Date(g.fecha);
          if (!isNaN(fecha.getTime())) {
            const mesIndex = fecha.getMonth();
            const mesNombre = meses[mesIndex];
            gastosPorMes[mesNombre] = (gastosPorMes[mesNombre] || 0) + (g.gastosConIVA || 0);
          }
        } catch (e) {
          console.warn('Fecha inválida:', g.fecha);
        }
      }
    });

    // Obtener presupuestos (si existen en datos originales, sino usar valores por defecto)
    const presupuestos = this.datosOriginales.gastosPresupuesto || meses.map(() => 5000); // Valor por defecto
    
    // Si hay filtros activos, usar los datos filtrados, sino usar los originales para el presupuesto
    const presupuestosData = this.datosOriginales.gastosPresupuesto || presupuestos;

    const labels = meses;
    const dataGastos = labels.map(m => gastosPorMes[m] || 0);
    const dataPresupuesto = labels.map((m, i) => presupuestosData[i] || 5000);

    // Crear configuración para gráfico de barras comparativo
    this.datosFiltrados.gastosVsPresupuestoConfig = {
      title: '',
      type: 'bar',
      labels,
      data: [
        {
          name: 'Gastos totales',
          data: dataGastos
        },
        {
          name: 'Presupuestado',
          data: dataPresupuesto
        }
      ],
      colors: ['#F19447', '#d3d3d3']
    };
  }

  recalcularGastosVsAnioAnterior(): void {
    if (!this.datosFiltrados.detalleGastos) return;

    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const gastosPorMes: { [key: string]: number } = {};
    
    // Calcular gastos del año actual por mes
    this.datosFiltrados.detalleGastos.forEach((g: any) => {
      if (g.fecha) {
        try {
          const fecha = new Date(g.fecha);
          if (!isNaN(fecha.getTime())) {
            const mesIndex = fecha.getMonth();
            const mesNombre = meses[mesIndex];
            gastosPorMes[mesNombre] = (gastosPorMes[mesNombre] || 0) + (g.gastosConIVA || 0);
          }
        } catch (e) {
          console.warn('Fecha inválida:', g.fecha);
        }
      }
    });

    // Obtener gastos del año anterior (si existen en datos originales, sino usar valores por defecto)
    const gastosAnioAnteriorData = this.datosOriginales.gastosAnioAnterior || meses.map(() => 4000); // Valor por defecto

    const labels = meses;
    const dataGastosActual = labels.map(m => gastosPorMes[m] || 0);
    const dataGastosAnterior = labels.map((m, i) => gastosAnioAnteriorData[i] || 4000);

    // Crear configuración para gráfico de barras comparativo
    this.datosFiltrados.gastosVsAnioAnteriorConfig = {
      title: '',
      type: 'bar',
      labels,
      data: [
        {
          name: 'Año actual',
          data: dataGastosActual
        },
        {
          name: 'Año anterior',
          data: dataGastosAnterior
        }
      ],
      colors: ['#F19447', '#d3d3d3']
    };
  }

  get detalleGastosRows(): any[] {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    if (!datos.detalleGastos) return [];
    const rows = datos.detalleGastos.map((gasto: any) => ({
      fecha: gasto.fecha || '-',
      proveedor: gasto.proveedor || '-',
      concepto: gasto.concepto || '-',
      documento: gasto.documento || '-',
      correlativo: gasto.correlativo || '-',
      gastosConIVA: gasto.gastosConIVA || 0, // Mantener como número para AG Grid
      isTotal: false
    }));
    
    return rows;
  }

  get totalDetalleGastos(): string {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    if (!datos.detalleGastos) return this.formatCurrency(0);
    const total = datos.detalleGastos.reduce((sum: number, gasto: any) => {
      return sum + (gasto.gastosConIVA || 0);
    }, 0);
    return this.formatCurrency(total);
  }

  // Getter para obtener los datos correctos (filtrados o originales)
  get datosParaVista(): any {
    return this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
  }

  // Helper para verificar si hay datos disponibles
  tieneDatos(): boolean {
    return !!(this.datos && 
              this.datos.detalleGastos && 
              Array.isArray(this.datos.detalleGastos) && 
              this.datos.detalleGastos.length > 0);
  }

}
