import { Component, Input, OnInit, OnChanges, Output, EventEmitter, ViewChild } from '@angular/core';
import { ColDef, GridOptions, GridApi, ColumnApi } from 'ag-grid-community';
import { AgGridAngular } from 'ag-grid-angular';

@Component({
  selector: 'app-finanzas',
  templateUrl: './finanzas.component.html',
  styleUrls: ['./finanzas.component.css']
})
export class FinanzasComponent implements OnInit, OnChanges {
  @Input() datos: any = {};
  @Output() filtrosCambiados = new EventEmitter<any>();

  // Datos originales (sin filtrar)
  datosOriginales: any = {};
  
  // Datos filtrados (se muestran en la vista)
  datosFiltrados: any = {};

  private inicializado: boolean = false;

  // Filtros
  anios = [2024, 2025, 2026];
  anioSeleccionado: number = 2024;
  
  sucursales = [
    { id: 'todas', nombre: 'Todas' },
    { id: '1', nombre: 'Sucursal 1' },
    { id: '2', nombre: 'Sucursal 2' }
  ];
  sucursalSeleccionada: string = 'todas';

  // Meses para la tabla
  meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

  // Estructura de datos para el estado de resultados
  estadoResultadosData: any[] = [];

  // Datos para análisis vertical
  analisisVerticalData: any[] = [];

  // Datos para análisis horizontal
  analisisHorizontalData: any[] = [];

  // Datos para flujo de efectivo
  flujoEfectivoData: any[] = [];

  // AG Grid references
  @ViewChild('estadoResultadosGrid') estadoResultadosGrid!: AgGridAngular;
  @ViewChild('analisisVerticalGrid') analisisVerticalGrid!: AgGridAngular;
  @ViewChild('analisisHorizontalGrid') analisisHorizontalGrid!: AgGridAngular;
  @ViewChild('flujoEfectivoGrid') flujoEfectivoGrid!: AgGridAngular;

  // AG Grid APIs
  private estadoResultadosGridApi!: GridApi;
  private analisisVerticalGridApi!: GridApi;
  private analisisHorizontalGridApi!: GridApi;
  private flujoEfectivoGridApi!: GridApi;

  // Quick filter texts
  quickFilterTextEstadoResultados: string = '';
  quickFilterTextVertical: string = '';
  quickFilterTextHorizontal: string = '';
  quickFilterTextFlujo: string = '';

  // AG Grid column definitions
  estadoResultadosColumnDefs: ColDef[] = [];
  analisisVerticalColumnDefs: ColDef[] = [];
  analisisHorizontalColumnDefs: ColDef[] = [];
  flujoEfectivoColumnDefs: ColDef[] = [];

  // AG Grid options
  estadoResultadosGridOptions: GridOptions = {};
  analisisVerticalGridOptions: GridOptions = {};
  analisisHorizontalGridOptions: GridOptions = {};
  flujoEfectivoGridOptions: GridOptions = {};

  // Datos para AG Grid (formato tree data)
  estadoResultadosTreeData: any[] = [];

  constructor() { }

  ngOnInit(): void {
    this.configurarAGGrid();
    
    // Intentar inicializar si ya hay datos
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.inicializarDatos();
    } else {
      // Si no hay datos, inicializar con datos de ejemplo
      this.inicializarEstadoResultados();
    }
    
    // Marcar como inicializado después de un pequeño delay
    setTimeout(() => {
      this.inicializado = true;
    }, 100);
  }

  configurarAGGrid(): void {
    // Configurar columnas para Estado de Resultados (Tree Data)
    this.estadoResultadosColumnDefs = [
      {
        field: 'nombre',
        headerName: 'Concepto',
        width: 300,
        pinned: 'left',
        cellRenderer: 'agGroupCellRenderer',
        cellStyle: (params: any): any => {
          if (params.data?.esUtilidadNeta) {
            return { fontWeight: 'bold', backgroundColor: '#66A3FF', color: '#ffffff' };
          }
          return null;
        }
      },
      ...this.meses.map(mes => ({
        field: mes,
        headerName: mes,
        width: 130,
        valueFormatter: (params: any) => {
          const valor = params.value || 0;
          return this.formatCurrency(valor);
        },
        cellStyle: (params: any) => {
          const valor = params.value || 0;
          const isUtilidadNeta = params.data?.esUtilidadNeta;
          const style: any = { textAlign: 'right' };
          
          if (isUtilidadNeta) {
            style.fontWeight = 'bold';
            style.backgroundColor = valor > 0 ? '#4caf50' : (valor < 0 ? '#f44336' : '#66A3FF');
            style.color = '#ffffff';
          } else if (valor < 0) {
            style.color = '#d32f2f';
          }
          
          return style;
        },
        type: 'numericColumn'
      }))
    ];

    // Configurar opciones de grid para Estado de Resultados
    this.estadoResultadosGridOptions = {
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true
      },
      treeData: true,
      getDataPath: (data: any) => {
        return data.path || [];
      },
      groupDefaultExpanded: -1, // Expandir todos los niveles por defecto
      enableCellTextSelection: true,
      ensureDomOrder: true,
      suppressExcelExport: false,
      suppressCsvExport: false,
      getRowClass: (params: any) => {
        if (params.data?.esUtilidadNeta) {
          return 'utilidad-neta-row';
        }
        return '';
      },
      onGridReady: (params: any) => {
        this.estadoResultadosGridApi = params.api;
        if (this.estadoResultadosTreeData && this.estadoResultadosTreeData.length > 0) {
          this.estadoResultadosGridApi.setRowData(this.estadoResultadosTreeData);
        }
      }
    };

    // Configurar columnas para Análisis Vertical
    this.analisisVerticalColumnDefs = [
      {
        field: 'concepto',
        headerName: 'Concepto',
        width: 250,
        pinned: 'left',
        cellRenderer: 'agGroupCellRenderer'
      },
      {
        field: 'valor',
        headerName: 'Valores',
        width: 150,
        valueFormatter: (params: any) => {
          return params.value ? this.formatCurrency(params.value) : '';
        },
        cellStyle: { textAlign: 'right' },
        type: 'numericColumn'
      },
      {
        field: 'porcentaje',
        headerName: 'Análisis vertical',
        width: 150,
        valueFormatter: (params: any) => {
          return params.value ? `${params.value}%` : '';
        },
        cellStyle: { textAlign: 'right' },
        type: 'numericColumn'
      }
    ];

    // Configurar columnas para Análisis Horizontal
    this.analisisHorizontalColumnDefs = [
      {
        field: 'concepto',
        headerName: 'Concepto',
        width: 200,
        pinned: 'left'
      },
      {
        field: 'mesAnterior',
        headerName: 'Mes anterior',
        width: 150,
        valueFormatter: (params: any) => {
          return params.value ? this.formatCurrency(params.value) : '';
        },
        cellStyle: { textAlign: 'right' },
        type: 'numericColumn'
      },
      {
        field: 'mesActual',
        headerName: 'Mes actual',
        width: 150,
        valueFormatter: (params: any) => {
          return params.value ? this.formatCurrency(params.value) : '';
        },
        cellStyle: { textAlign: 'right' },
        type: 'numericColumn'
      },
      {
        field: 'variacion',
        headerName: 'Variación',
        width: 150,
        valueFormatter: (params: any) => {
          return params.value ? this.formatCurrency(params.value) : '';
        },
        cellStyle: (params: any) => {
          return {
            textAlign: 'right',
            color: params.value < 0 ? '#d32f2f' : '#333'
          };
        },
        type: 'numericColumn'
      },
      {
        field: 'variacionPorcentaje',
        headerName: 'Variación %',
        width: 150,
        valueFormatter: (params: any) => {
          return params.value ? `${this.formatPercentage(params.value)}%` : '';
        },
        cellStyle: (params: any) => {
          return {
            textAlign: 'right',
            color: params.value < 0 ? '#d32f2f' : '#333'
          };
        },
        type: 'numericColumn'
      }
    ];

    // Configurar columnas para Flujo de Efectivo
    this.flujoEfectivoColumnDefs = [
      {
        field: 'concepto',
        headerName: 'Concepto',
        width: 200,
        pinned: 'left',
        cellStyle: (params: any) => {
          return {
            fontWeight: params.data?.esEfectivoDisponible ? '600' : 'normal',
            backgroundColor: params.data?.esEfectivoDisponible ? '#e3f2fd' : 'inherit'
          };
        }
      },
      ...this.meses.map(mes => ({
        field: mes,
        headerName: mes,
        width: 120,
        valueFormatter: (params: any) => {
          const valor = params.value || 0;
          return valor ? this.formatCurrency(valor) : '';
        },
        cellStyle: (params: any) => {
          const valor = params.value || 0;
          return {
            textAlign: 'right',
            color: valor < 0 ? '#d32f2f' : '#333',
            backgroundColor: params.data?.esEfectivoDisponible ? '#e3f2fd' : 'inherit'
          };
        },
        type: 'numericColumn'
      }))
    ];

    // Configurar opciones de grid
    const defaultGridOptions: GridOptions = {
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true
      },
      enableCellTextSelection: true,
      ensureDomOrder: true,
      suppressExcelExport: false,
      suppressCsvExport: false,
    };

    this.analisisVerticalGridOptions = { ...defaultGridOptions };
    this.analisisHorizontalGridOptions = { ...defaultGridOptions };
    this.flujoEfectivoGridOptions = { ...defaultGridOptions };
  }

  onGridReadyVertical(params: any): void {
    this.analisisVerticalGridApi = params.api;
    this.analisisVerticalGridApi.sizeColumnsToFit();
  }

  onGridReadyHorizontal(params: any): void {
    this.analisisHorizontalGridApi = params.api;
    this.analisisHorizontalGridApi.sizeColumnsToFit();
  }

  onGridReadyFlujo(params: any): void {
    this.flujoEfectivoGridApi = params.api;
    this.flujoEfectivoGridApi.sizeColumnsToFit();
  }

  onQuickFilterChangeVertical(): void {
    if (this.analisisVerticalGridApi) {
      this.analisisVerticalGridApi.setQuickFilter(this.quickFilterTextVertical);
    }
  }

  onQuickFilterChangeHorizontal(): void {
    if (this.analisisHorizontalGridApi) {
      this.analisisHorizontalGridApi.setQuickFilter(this.quickFilterTextHorizontal);
    }
  }

  onQuickFilterChangeFlujo(): void {
    if (this.flujoEfectivoGridApi) {
      this.flujoEfectivoGridApi.setQuickFilter(this.quickFilterTextFlujo);
    }
  }

  exportarCSVVertical(): void {
    if (this.analisisVerticalGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.analisisVerticalGridApi.exportDataAsCsv({
        fileName: `analisis-vertical-${fecha}.csv`
      });
    }
  }

  exportarExcelVertical(): void {
    if (this.analisisVerticalGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.analisisVerticalGridApi.exportDataAsCsv({
        fileName: `analisis-vertical-${fecha}.csv`
      });
    }
  }

  exportarCSVHorizontal(): void {
    if (this.analisisHorizontalGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.analisisHorizontalGridApi.exportDataAsCsv({
        fileName: `analisis-horizontal-${fecha}.csv`
      });
    }
  }

  exportarExcelHorizontal(): void {
    if (this.analisisHorizontalGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.analisisHorizontalGridApi.exportDataAsCsv({
        fileName: `analisis-horizontal-${fecha}.csv`
      });
    }
  }

  exportarCSVFlujo(): void {
    if (this.flujoEfectivoGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.flujoEfectivoGridApi.exportDataAsCsv({
        fileName: `flujo-efectivo-${fecha}.csv`
      });
    }
  }

  exportarExcelFlujo(): void {
    if (this.flujoEfectivoGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.flujoEfectivoGridApi.exportDataAsCsv({
        fileName: `flujo-efectivo-${fecha}.csv`
      });
    }
  }

  limpiarFiltrosVertical(): void {
    if (this.analisisVerticalGridApi) {
      this.analisisVerticalGridApi.setFilterModel(null);
      this.quickFilterTextVertical = '';
      this.analisisVerticalGridApi.setQuickFilter('');
    }
  }

  limpiarFiltrosHorizontal(): void {
    if (this.analisisHorizontalGridApi) {
      this.analisisHorizontalGridApi.setFilterModel(null);
      this.quickFilterTextHorizontal = '';
      this.analisisHorizontalGridApi.setQuickFilter('');
    }
  }

  limpiarFiltrosFlujo(): void {
    if (this.flujoEfectivoGridApi) {
      this.flujoEfectivoGridApi.setFilterModel(null);
      this.quickFilterTextFlujo = '';
      this.flujoEfectivoGridApi.setQuickFilter('');
    }
  }

  ngOnChanges(changes: any): void {
    if (changes['datos'] && this.datos && Object.keys(this.datos).length > 0) {
      this.inicializarDatos();
    }
  }

  inicializarDatos(): void {
    if (this.datos && Object.keys(this.datos).length > 0) {
      // Guardar datos originales
      this.datosOriginales = JSON.parse(JSON.stringify(this.datos));
      // Inicializar datos filtrados
      this.datosFiltrados = JSON.parse(JSON.stringify(this.datos));
      this.datos = this.datosFiltrados;
      this.inicializado = true;
      
      // Inicializar todas las estructuras de datos
      this.inicializarEstadoResultados();
      
      // Recalcular todos los datos basados en los datos filtrados
      this.recalcularTodosLosDatos();
    }
  }

  recalcularTodosLosDatos(): void {
    // Recalcular estado de resultados
    this.recalcularEstadoResultados();
    
    // Recalcular análisis vertical
    this.recalcularAnalisisVertical();
    
    // Recalcular análisis horizontal
    this.recalcularAnalisisHorizontal();
    
    // Recalcular flujo de efectivo
    this.recalcularFlujoEfectivo();
    
    // Recalcular métricas (tarjetas)
    this.recalcularMetricas();
    
    // Recalcular gráficos
    this.recalcularGraficos();
  }

  inicializarEstadoResultados(): void {
    // Si hay datos del backend, usarlos; si no, usar datos de ejemplo
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    if (datos && datos.estadoResultados) {
      this.estadoResultadosData = this.procesarDatosEstadoResultados(datos.estadoResultados);
    } else {
      // Datos de ejemplo basados en la imagen
      this.estadoResultadosData = this.getDatosEjemplo();
    }
    
    // Convertir a formato tree data para AG Grid
    this.estadoResultadosTreeData = this.convertirEstadoResultadosATreeData(this.estadoResultadosData);

    // Inicializar datos de análisis
    this.inicializarAnalisisVertical();
    this.inicializarAnalisisHorizontal();
    this.inicializarFlujoEfectivo();
  }

  recalcularEstadoResultados(): void {
    // Recalcular estado de resultados basado en datos filtrados
    if (this.datosFiltrados && this.datosFiltrados.estadoResultados) {
      this.estadoResultadosData = this.procesarDatosEstadoResultados(this.datosFiltrados.estadoResultados);
    } else if (this.datosFiltrados && this.datosFiltrados.detalleGastos && this.datosFiltrados.detalleVentas) {
      // Si tenemos detalle de gastos y ventas, calcular estado de resultados
      this.estadoResultadosData = this.calcularEstadoResultadosDesdeDetalle();
    }
    
    // Convertir a formato tree data para AG Grid
    this.estadoResultadosTreeData = this.convertirEstadoResultadosATreeData(this.estadoResultadosData);
    
    // Actualizar grid si está listo
    if (this.estadoResultadosGridApi) {
      this.estadoResultadosGridApi.setRowData(this.estadoResultadosTreeData);
    }
  }

  calcularEstadoResultadosDesdeDetalle(): any[] {
    // Calcular estado de resultados desde detalle de gastos y ventas
    const meses = this.meses;
    const estadoResultados: any[] = [];
    
    // Calcular ventas por mes
    const ventasPorMes: { [key: string]: number } = {};
    if (this.datosFiltrados.detalleVentas) {
      this.datosFiltrados.detalleVentas.forEach((venta: any) => {
        if (venta.fecha) {
          const fecha = new Date(venta.fecha);
          const mes = meses[fecha.getMonth()];
          ventasPorMes[mes] = (ventasPorMes[mes] || 0) + (venta.totalConIVA || 0);
        }
      });
    }
    
    // Calcular costos por mes
    const costosPorMes: { [key: string]: number } = {};
    if (this.datosFiltrados.detalleGastos) {
      this.datosFiltrados.detalleGastos.forEach((gasto: any) => {
        if (gasto.fecha) {
          const fecha = new Date(gasto.fecha);
          const mes = meses[fecha.getMonth()];
          costosPorMes[mes] = (costosPorMes[mes] || 0) + (gasto.gastosConIVA || 0);
        }
      });
    }
    
    // Construir estructura de estado de resultados
    const valoresVentas: { [key: string]: number } = {};
    const valoresCostos: { [key: string]: number } = {};
    const valoresUtilidadBruta: { [key: string]: number } = {};
    const valoresUtilidadNeta: { [key: string]: number } = {};
    
    meses.forEach(mes => {
      valoresVentas[mes] = ventasPorMes[mes] || 0;
      valoresCostos[mes] = costosPorMes[mes] || 0;
      valoresUtilidadBruta[mes] = valoresVentas[mes] - valoresCostos[mes];
      valoresUtilidadNeta[mes] = valoresUtilidadBruta[mes]; // Simplificado, sin gastos adicionales
    });
    
    return [
      {
        nombre: 'Ventas',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: valoresVentas
      },
      {
        nombre: 'Costo de ventas',
        nivel: 0,
        expandido: true,
        tieneHijos: true,
        hijos: [
          {
            nombre: 'Costo productos vendidos',
            nivel: 1,
            expandido: false,
            tieneHijos: false,
            hijos: [],
            valores: valoresCostos
          }
        ],
        valores: valoresCostos
      },
      {
        nombre: 'Utilidad bruta',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: valoresUtilidadBruta
      },
      {
        nombre: 'Gastos administrativos',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {}
      },
      {
        nombre: 'Gastos operativos',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {}
      },
      {
        nombre: 'Gastos comerciales',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {}
      },
      {
        nombre: 'Gastos financieros',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {}
      },
      {
        nombre: 'Utilidad neta',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        esUtilidadNeta: true,
        valores: valoresUtilidadNeta
      }
    ];
  }

  recalcularMetricas(): void {
    // Recalcular métricas de las tarjetas basado en datos filtrados
    if (!this.datosFiltrados) return;
    
    let ventasTotales = 0;
    let gastosTotales = 0;
    
    if (this.datosFiltrados.detalleVentas) {
      ventasTotales = this.datosFiltrados.detalleVentas.reduce((sum: number, v: any) => 
        sum + (v.totalConIVA || 0), 0);
    }
    
    if (this.datosFiltrados.detalleGastos) {
      gastosTotales = this.datosFiltrados.detalleGastos.reduce((sum: number, g: any) => 
        sum + (g.gastosConIVA || 0), 0);
    }
    
    const resultados = ventasTotales - gastosTotales;
    const margen = ventasTotales > 0 ? ((resultados / ventasTotales) * 100) : 0;
    
    if (!this.datosFiltrados.ventasTotalesConIVA) {
      this.datosFiltrados.ventasTotalesConIVA = ventasTotales;
    } else {
      this.datosFiltrados.ventasTotalesConIVA = ventasTotales;
    }
    
    if (!this.datosFiltrados.gastosTotalesConIVA) {
      this.datosFiltrados.gastosTotalesConIVA = gastosTotales;
    } else {
      this.datosFiltrados.gastosTotalesConIVA = gastosTotales;
    }
    
    this.datosFiltrados.resultados = resultados;
    this.datosFiltrados.margen = margen;
  }

  recalcularGraficos(): void {
    // Recalcular gráficos de ventas y gastos por mes
    this.recalcularVentasGastosConfig();
    this.recalcularUtilidadPorMesConfig();
  }

  recalcularVentasGastosConfig(): void {
    if (!this.datosFiltrados) return;
    
    const meses = this.meses;
    const ventasPorMes: number[] = [];
    const gastosPorMes: number[] = [];
    
    meses.forEach((mes, index) => {
      let ventas = 0;
      let gastos = 0;
      
      if (this.datosFiltrados.detalleVentas) {
        this.datosFiltrados.detalleVentas.forEach((venta: any) => {
          if (venta.fecha) {
            const fecha = new Date(venta.fecha);
            if (fecha.getMonth() === index) {
              ventas += (venta.totalConIVA || 0);
            }
          }
        });
      }
      
      if (this.datosFiltrados.detalleGastos) {
        this.datosFiltrados.detalleGastos.forEach((gasto: any) => {
          if (gasto.fecha) {
            const fecha = new Date(gasto.fecha);
            if (fecha.getMonth() === index) {
              gastos += (gasto.gastosConIVA || 0);
            }
          }
        });
      }
      
      ventasPorMes.push(ventas);
      gastosPorMes.push(gastos);
    });
    
    this.datosFiltrados.ventasGastosConfig = {
      title: '',
      labels: meses,
      data: [
        {
          name: 'Ventas con IVA',
          data: ventasPorMes
        },
        {
          name: 'Gastos con IVA',
          data: gastosPorMes
        }
      ],
      type: 'bar',
      colors: ['#5470c6', '#ff9800']
    };
  }

  recalcularUtilidadPorMesConfig(): void {
    if (!this.datosFiltrados || !this.datosFiltrados.ventasGastosConfig) return;
    
    const meses = this.meses;
    const utilidadPorMes: number[] = [];
    const utilidadOriginal: number[] = []; // Guardar valores originales para colores
    
    const ventasData = this.datosFiltrados.ventasGastosConfig.data[0].data;
    const gastosData = this.datosFiltrados.ventasGastosConfig.data[1].data;
    
    meses.forEach((_, index) => {
      const utilidad = (ventasData[index] || 0) - (gastosData[index] || 0);
      utilidadOriginal.push(utilidad);
      // Usar valor absoluto para mostrar siempre valores positivos
      utilidadPorMes.push(Math.abs(utilidad));
    });
    
    // Guardar valores originales para que el componente de gráfico pueda usar colores condicionales
    (this.datosFiltrados.utilidadPorMesConfig as any) = {
      title: '',
      labels: meses,
      data: utilidadPorMes,
      type: 'bar',
      colors: ['#4caf50'],
      conditionalColors: true,
      originalValues: utilidadOriginal // Valores originales para determinar colores
    };
  }

  inicializarAnalisisVertical(): void {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    if (datos && datos.analisisVertical) {
      this.analisisVerticalData = datos.analisisVertical;
    } else {
      // Calcular desde datos filtrados o usar ejemplo
      this.recalcularAnalisisVertical();
    }
  }

  recalcularAnalisisVertical(): void {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    
    if (datos && datos.analisisVertical) {
      this.analisisVerticalData = datos.analisisVertical;
      return;
    }
    
    // Calcular desde datos disponibles
    const ventasTotales = datos?.ventasTotalesConIVA || datos?.resultados?.ventasTotales || 0;
    const gastosTotales = datos?.gastosTotalesConIVA || datos?.resultados?.gastosTotales || 0;
    const utilidadBruta = ventasTotales - gastosTotales;
    const utilidadNeta = datos?.resultados || utilidadBruta;
    
    if (ventasTotales > 0) {
      this.analisisVerticalData = [
        { concepto: 'Ventas', valor: ventasTotales, porcentaje: 100 },
        { concepto: 'Costo de ventas', valor: gastosTotales, porcentaje: (gastosTotales / ventasTotales) * 100 },
        { concepto: 'Utilidad bruta', valor: utilidadBruta, porcentaje: (utilidadBruta / ventasTotales) * 100 },
        { concepto: 'Gastos administrativos', valor: 0, porcentaje: 0 },
        { concepto: 'Gastos operativos', valor: 0, porcentaje: 0 },
        { concepto: 'Gastos comerciales', valor: 0, porcentaje: 0 },
        { concepto: 'Gastos financieros', valor: 0, porcentaje: 0 },
        { concepto: 'Utilidad neta', valor: utilidadNeta, porcentaje: (utilidadNeta / ventasTotales) * 100 }
      ];
    } else {
      // Datos de ejemplo
      this.analisisVerticalData = [
        { concepto: 'Ventas', valor: 3501682.66, porcentaje: 100 },
        { concepto: 'Costo de ventas', valor: 21801.39, porcentaje: 1 },
        { concepto: 'Utilidad bruta', valor: 3479881.27, porcentaje: 99 },
        { concepto: 'Gastos administrativos', valor: 5048.37, porcentaje: 0 },
        { concepto: 'Gastos operativos', valor: 17588.96, porcentaje: 1 },
        { concepto: 'Gastos comerciales', valor: 200.00, porcentaje: 0 },
        { concepto: 'Gastos financieros', valor: 3355.95, porcentaje: 0 },
        { concepto: 'Utilidad neta', valor: 3453687.99, porcentaje: 99 }
      ];
    }
  }

  inicializarAnalisisHorizontal(): void {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    if (datos && datos.analisisHorizontal) {
      this.analisisHorizontalData = datos.analisisHorizontal;
    } else {
      // Calcular desde datos filtrados o usar ejemplo
      this.recalcularAnalisisHorizontal();
    }
  }

  recalcularAnalisisHorizontal(): void {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    
    if (datos && datos.analisisHorizontal) {
      this.analisisHorizontalData = datos.analisisHorizontal;
      return;
    }
    
    // Calcular comparación mes anterior vs mes actual
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();
    const mesAnterior = mesActual === 0 ? 11 : mesActual - 1;
    const añoAnterior = mesActual === 0 ? añoActual - 1 : añoActual;
    
    let ventasMesActual = 0;
    let ventasMesAnterior = 0;
    let gastosMesActual = 0;
    let gastosMesAnterior = 0;
    
    if (datos?.detalleVentas) {
      datos.detalleVentas.forEach((venta: any) => {
        if (venta.fecha) {
          const fecha = new Date(venta.fecha);
          if (fecha.getMonth() === mesActual && fecha.getFullYear() === añoActual) {
            ventasMesActual += (venta.totalConIVA || 0);
          } else if (fecha.getMonth() === mesAnterior && fecha.getFullYear() === añoAnterior) {
            ventasMesAnterior += (venta.totalConIVA || 0);
          }
        }
      });
    }
    
    if (datos?.detalleGastos) {
      datos.detalleGastos.forEach((gasto: any) => {
        if (gasto.fecha) {
          const fecha = new Date(gasto.fecha);
          if (fecha.getMonth() === mesActual && fecha.getFullYear() === añoActual) {
            gastosMesActual += (gasto.gastosConIVA || 0);
          } else if (fecha.getMonth() === mesAnterior && fecha.getFullYear() === añoAnterior) {
            gastosMesAnterior += (gasto.gastosConIVA || 0);
          }
        }
      });
    }
    
    const utilidadBrutaActual = ventasMesActual - gastosMesActual;
    const utilidadBrutaAnterior = ventasMesAnterior - gastosMesAnterior;
    
    const calcularVariacion = (actual: number, anterior: number) => {
      return actual - anterior;
    };
    
    const calcularVariacionPorcentaje = (actual: number, anterior: number) => {
      if (anterior === 0) return actual > 0 ? 100 : (actual < 0 ? -100 : 0);
      return ((actual - anterior) / anterior) * 100;
    };
    
    this.analisisHorizontalData = [
      { 
        concepto: 'Ventas', 
        mesAnterior: ventasMesAnterior, 
        mesActual: ventasMesActual, 
        variacion: calcularVariacion(ventasMesActual, ventasMesAnterior), 
        variacionPorcentaje: calcularVariacionPorcentaje(ventasMesActual, ventasMesAnterior)
      },
      { 
        concepto: 'Costo de ventas', 
        mesAnterior: gastosMesAnterior, 
        mesActual: gastosMesActual, 
        variacion: calcularVariacion(gastosMesActual, gastosMesAnterior), 
        variacionPorcentaje: calcularVariacionPorcentaje(gastosMesActual, gastosMesAnterior)
      },
      { 
        concepto: 'Utilidad bruta', 
        mesAnterior: utilidadBrutaAnterior, 
        mesActual: utilidadBrutaActual, 
        variacion: calcularVariacion(utilidadBrutaActual, utilidadBrutaAnterior), 
        variacionPorcentaje: calcularVariacionPorcentaje(utilidadBrutaActual, utilidadBrutaAnterior)
      },
      { 
        concepto: 'Gastos administrativos', 
        mesAnterior: 0, 
        mesActual: 0, 
        variacion: 0, 
        variacionPorcentaje: 0
      },
      { 
        concepto: 'Gastos operativos', 
        mesAnterior: 0, 
        mesActual: 0, 
        variacion: 0, 
        variacionPorcentaje: 0
      },
      { 
        concepto: 'Gastos financieros', 
        mesAnterior: 0, 
        mesActual: 0, 
        variacion: 0, 
        variacionPorcentaje: 0
      }
    ];
  }

  inicializarFlujoEfectivo(): void {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    if (datos && datos.flujoEfectivo && Array.isArray(datos.flujoEfectivo)) {
      // Si viene como array plano, usarlo directamente
      if (datos.flujoEfectivo.length > 0 && datos.flujoEfectivo[0].concepto) {
        this.flujoEfectivoData = datos.flujoEfectivo;
        return;
      }
    }
    // Calcular desde datos filtrados o usar ejemplo
    this.recalcularFlujoEfectivo();
  }

  recalcularFlujoEfectivo(): void {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    
    if (datos && datos.flujoEfectivo) {
      // Convertir a formato plano para AG Grid
      const datosRaw = datos.flujoEfectivo;
      this.flujoEfectivoData = datosRaw.map((item: any) => {
        const row: any = {
          concepto: item.concepto,
          esEfectivoDisponible: item.esEfectivoDisponible
        };
        this.meses.forEach(mes => {
          row[mes] = (item.valores as any)[mes] || 0;
        });
        return row;
      });
      return;
    }
    
    // Calcular desde detalle de ventas y gastos
    if (datos?.detalleVentas || datos?.detalleGastos) {
      const meses = this.meses;
      const ventasPorMes: { [key: string]: number } = {};
      const comprasPorMes: { [key: string]: number } = {};
      const gastosPorMes: { [key: string]: number } = {};
      
      // Calcular ventas por mes
      if (datos.detalleVentas) {
        datos.detalleVentas.forEach((venta: any) => {
          if (venta.fecha) {
            const fecha = new Date(venta.fecha);
            const mes = meses[fecha.getMonth()];
            ventasPorMes[mes] = (ventasPorMes[mes] || 0) + (venta.totalConIVA || 0);
          }
        });
      }
      
      // Calcular compras y gastos por mes
      if (datos.detalleGastos) {
        datos.detalleGastos.forEach((gasto: any) => {
          if (gasto.fecha) {
            const fecha = new Date(gasto.fecha);
            const mes = meses[fecha.getMonth()];
            const monto = gasto.gastosConIVA || 0;
            // Simplificado: todos los gastos van a "Gastos"
            gastosPorMes[mes] = (gastosPorMes[mes] || 0) + monto;
          }
        });
      }
      
      // Calcular efectivo disponible (ventas - compras - gastos)
      const efectivoDisponiblePorMes: { [key: string]: number } = {};
      meses.forEach(mes => {
        efectivoDisponiblePorMes[mes] = (ventasPorMes[mes] || 0) - (comprasPorMes[mes] || 0) - (gastosPorMes[mes] || 0);
      });
      
      // Convertir a formato plano para AG Grid
      const datosRaw = [
        {
          concepto: 'Ventas',
          esEfectivoDisponible: false,
          valores: ventasPorMes
        },
        {
          concepto: 'Compras',
          esEfectivoDisponible: false,
          valores: comprasPorMes
        },
        {
          concepto: 'Gastos',
          esEfectivoDisponible: false,
          valores: gastosPorMes
        },
        {
          concepto: 'Efectivo disponible',
          esEfectivoDisponible: true,
          valores: efectivoDisponiblePorMes
        }
      ];
      
      this.flujoEfectivoData = datosRaw.map(item => {
        const row: any = {
          concepto: item.concepto,
          esEfectivoDisponible: item.esEfectivoDisponible
        };
        this.meses.forEach(mes => {
          row[mes] = (item.valores as any)[mes] || 0;
        });
        return row;
      });
      return;
    }
    
    // Si no hay datos, usar ejemplo
    if (!datos || !datos.flujoEfectivo) {
      // Datos de ejemplo - convertir a formato plano para AG Grid
      const datosRaw = [
        {
          concepto: 'Ventas',
          esEfectivoDisponible: false,
          valores: {
            'Enero': 22563.68,
            'Febrero': 3163.89,
            'Marzo': 23179.61,
            'Abril': 10098.80,
            'Mayo': 20163.53,
            'Junio': 5660.51,
            'Julio': 7239.71,
            'Agosto': 6447.00,
            'Septiembre': 0,
            'Octubre': 1714.22,
            'Noviembre': 3391416.00,
            'Diciembre': 0
          }
        },
        {
          concepto: 'Compras',
          esEfectivoDisponible: false,
          valores: {
            'Enero': 1336.21,
            'Febrero': 811.34,
            'Marzo': 350.24,
            'Abril': 0,
            'Mayo': 0,
            'Junio': 0,
            'Julio': 0,
            'Agosto': 0,
            'Septiembre': 0,
            'Octubre': 6852.85,
            'Noviembre': 5165.34,
            'Diciembre': 0
          }
        },
        {
          concepto: 'Gastos',
          esEfectivoDisponible: false,
          valores: {
            'Enero': 190.00,
            'Febrero': 3913.00,
            'Marzo': 0,
            'Abril': 0,
            'Mayo': 0,
            'Junio': 0,
            'Julio': 0,
            'Agosto': 0,
            'Septiembre': 0,
            'Octubre': 3217.67,
            'Noviembre': 2029.55,
            'Diciembre': 0
          }
        },
        {
          concepto: 'Efectivo disponible',
          esEfectivoDisponible: true,
          valores: {
            'Enero': 21037.47,
            'Febrero': -1560.45,
            'Marzo': 22829.37,
            'Abril': 10098.80,
            'Mayo': 20163.53,
            'Junio': 5660.51,
            'Julio': 7239.71,
            'Agosto': 6447.00,
            'Septiembre': 0,
            'Octubre': -8356.30,
            'Noviembre': 3384221.11,
            'Diciembre': 0
          }
        }
      ];

      // Convertir a formato plano para AG Grid
      this.flujoEfectivoData = datosRaw.map(item => {
        const row: any = {
          concepto: item.concepto,
          esEfectivoDisponible: item.esEfectivoDisponible
        };
        this.meses.forEach(mes => {
          row[mes] = (item.valores as any)[mes] || 0;
        });
        return row;
      });
    }
  }

  procesarDatosEstadoResultados(datos: any): any[] {
    // Procesar datos del backend y convertirlos al formato necesario
    return datos.map((item: any) => ({
      nombre: item.nombre,
      nivel: item.nivel || 0,
      expandido: item.expandido || false,
      tieneHijos: item.hijos && item.hijos.length > 0,
      hijos: item.hijos ? this.procesarDatosEstadoResultados(item.hijos) : [],
      valores: item.valores || {},
      esUtilidadNeta: item.nombre === 'Utilidad neta' || item.esUtilidadNeta || false
    }));
  }

  /**
   * Convierte los datos jerárquicos a formato tree data de AG Grid
   */
  convertirEstadoResultadosATreeData(datos: any[], path: string[] = []): any[] {
    const treeData: any[] = [];
    
    datos.forEach((item: any) => {
      const currentPath = [...path, item.nombre];
      const row: any = {
        nombre: item.nombre,
        path: currentPath,
        esUtilidadNeta: item.esUtilidadNeta || false
      };
      
      // Agregar valores de cada mes como campos
      this.meses.forEach(mes => {
        row[mes] = (item.valores && item.valores[mes]) ? item.valores[mes] : 0;
      });
      
      // Si tiene hijos, agregarlos como children (AG Grid tree data)
      if (item.hijos && item.hijos.length > 0) {
        row.children = this.convertirEstadoResultadosATreeData(item.hijos, currentPath);
      }
      
      treeData.push(row);
    });
    
    return treeData;
  }

  getDatosEjemplo(): any[] {
    return [
      {
        nombre: 'Ventas',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {
          'Enero': 22563.68,
          'Febrero': 3163.89,
          'Marzo': 23179.61,
          'Abril': 10098.80,
          'Mayo': 20163.53,
          'Junio': 5660.51,
          'Julio': 7239.71,
          'Agosto': 6447.00,
          'Septiembre': -592.58,
          'Octubre': 1714.22,
          'Noviembre': 3391416.33,
          'Diciembre': 10627.96
        }
      },
      {
        nombre: 'Costo de ventas',
        nivel: 0,
        expandido: true,
        tieneHijos: true,
        hijos: [
          {
            nombre: 'Costo productos vendidos',
            nivel: 1,
            expandido: false,
            tieneHijos: false,
            hijos: [],
            valores: {
              'Enero': 3533.86,
              'Febrero': 4724.34,
              'Marzo': 350.24,
              'Abril': 1852.60,
              'Mayo': 15319.36,
              'Junio': 200.00,
              'Julio': 602.99,
              'Agosto': 6974.26,
              'Septiembre': 5548.15,
              'Octubre': 13559.77,
              'Noviembre': 7954.89,
              'Diciembre': 12773.03
            }
          }
        ],
        valores: {
          'Enero': 3533.86,
          'Febrero': 4724.34,
          'Marzo': 350.24,
          'Abril': 1852.60,
          'Mayo': 15319.36,
          'Junio': 200.00,
          'Julio': 602.99,
          'Agosto': 6974.26,
          'Septiembre': 5548.15,
          'Octubre': 13559.77,
          'Noviembre': 7954.89,
          'Diciembre': 12773.03
        }
      },
      {
        nombre: 'Utilidad bruta',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {
          'Enero': 19029.82,
          'Febrero': -1560.45,
          'Marzo': 22829.37,
          'Abril': 8246.20,
          'Mayo': 4844.17,
          'Junio': 5460.51,
          'Julio': 6636.72,
          'Agosto': -527.26,
          'Septiembre': -6140.73,
          'Octubre': -11845.55,
          'Noviembre': 3383461.44,
          'Diciembre': -2145.07
        }
      },
      {
        nombre: 'Gastos administrativos',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {}
      },
      {
        nombre: 'Gastos operativos',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {}
      },
      {
        nombre: 'Gastos comerciales',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {
          'Abril': 1538.53
        }
      },
      {
        nombre: 'Gastos financieros',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: {}
      },
      {
        nombre: 'Utilidad neta',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        esUtilidadNeta: true, // Flag especial para identificar esta fila
        valores: {
          'Enero': 19767.49,
          'Febrero': -1401.01,
          'Marzo': 22602.61,
          'Abril': 8014.80,
          'Mayo': 12771.63,
          'Junio': 3003.01,
          'Julio': 4266.96,
          'Agosto': 1996.27,
          'Septiembre': -3801.51,
          'Octubre': -3174.30,
          'Noviembre': 3384896.88,
          'Diciembre': 4745.16
        }
      }
    ];
  }

  toggleCategoria(categoria: any): void {
    categoria.expandido = !categoria.expandido;
  }

  expandirTodo(): void {
    if (this.estadoResultadosGridApi) {
      this.estadoResultadosGridApi.expandAll();
    } else {
      // Fallback para cuando el grid no está listo - actualizar datos para cuando se inicialice
      this.estadoResultadosData.forEach(categoria => {
        categoria.expandido = true;
        if (categoria.hijos) {
          categoria.hijos.forEach((hijo: any) => {
            hijo.expandido = true;
          });
        }
      });
      this.estadoResultadosTreeData = this.convertirEstadoResultadosATreeData(this.estadoResultadosData);
    }
  }

  colapsarTodo(): void {
    if (this.estadoResultadosGridApi) {
      this.estadoResultadosGridApi.collapseAll();
    } else {
      // Fallback para cuando el grid no está listo - actualizar datos para cuando se inicialice
      this.estadoResultadosData.forEach(categoria => {
        categoria.expandido = false;
        if (categoria.hijos) {
          categoria.hijos.forEach((hijo: any) => {
            hijo.expandido = false;
          });
        }
      });
      this.estadoResultadosTreeData = this.convertirEstadoResultadosATreeData(this.estadoResultadosData);
    }
  }

  onGridReadyEstadoResultados(params: any): void {
    this.estadoResultadosGridApi = params.api;
    if (this.estadoResultadosTreeData && this.estadoResultadosTreeData.length > 0) {
      this.estadoResultadosGridApi.setRowData(this.estadoResultadosTreeData);
    }
  }

  onQuickFilterChangeEstadoResultados(): void {
    if (this.estadoResultadosGridApi) {
      this.estadoResultadosGridApi.setQuickFilter(this.quickFilterTextEstadoResultados);
    }
  }

  exportarCSVEstadoResultados(): void {
    if (this.estadoResultadosGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.estadoResultadosGridApi.exportDataAsCsv({
        fileName: `estado-resultados-${fecha}.csv`
      });
    }
  }

  exportarExcelEstadoResultados(): void {
    if (this.estadoResultadosGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.estadoResultadosGridApi.exportDataAsCsv({
        fileName: `estado-resultados-${fecha}.csv`
      });
    }
  }

  limpiarFiltrosEstadoResultados(): void {
    if (this.estadoResultadosGridApi) {
      this.estadoResultadosGridApi.setFilterModel(null);
      this.quickFilterTextEstadoResultados = '';
      this.estadoResultadosGridApi.setQuickFilter('');
    }
  }

  cambiarAnio(anio: number): void {
    this.anioSeleccionado = anio;
    this.aplicarFiltros();
  }

  cambiarSucursal(): void {
    this.aplicarFiltros();
  }

  aplicarFiltros(): void {
    // No emitir durante la inicialización
    if (!this.inicializado) {
      return;
    }
    
    const filtros = {
      anio: this.anioSeleccionado,
      sucursal: this.sucursalSeleccionada
    };
    
    // Si tenemos datos originales, aplicar filtros localmente
    if (Object.keys(this.datosOriginales).length > 0) {
      this.aplicarFiltrosLocales(filtros);
    }
    
    // Emitir evento al componente padre para recargar datos
    this.filtrosCambiados.emit(filtros);
  }

  aplicarFiltrosLocales(filtros: any): void {
    // Restaurar datos originales
    this.datosFiltrados = JSON.parse(JSON.stringify(this.datosOriginales));
    
    // Aplicar filtros a los datos
    if (filtros.sucursal && filtros.sucursal !== 'todas') {
      // Filtrar por sucursal si aplica
      if (this.datosFiltrados.detalleVentas) {
        this.datosFiltrados.detalleVentas = this.datosFiltrados.detalleVentas.filter((v: any) => 
          v.sucursalId === filtros.sucursal
        );
      }
      if (this.datosFiltrados.detalleGastos) {
        this.datosFiltrados.detalleGastos = this.datosFiltrados.detalleGastos.filter((g: any) => 
          g.sucursalId === filtros.sucursal
        );
      }
    }
    
    // Filtrar por año si aplica
    if (filtros.anio) {
      const añoFiltro = filtros.anio;
      if (this.datosFiltrados.detalleVentas) {
        this.datosFiltrados.detalleVentas = this.datosFiltrados.detalleVentas.filter((v: any) => {
          if (v.fecha) {
            const fecha = new Date(v.fecha);
            return fecha.getFullYear() === añoFiltro;
          }
          return false;
        });
      }
      if (this.datosFiltrados.detalleGastos) {
        this.datosFiltrados.detalleGastos = this.datosFiltrados.detalleGastos.filter((g: any) => {
          if (g.fecha) {
            const fecha = new Date(g.fecha);
            return fecha.getFullYear() === añoFiltro;
          }
          return false;
        });
      }
    }
    
    // Recalcular todos los datos con los filtros aplicados
    this.recalcularTodosLosDatos();
    
    // Actualizar referencia
    this.datos = { ...this.datosFiltrados };
  }

  formatCurrency(value: number): string {
    if (value === 0 || value === null || value === undefined) {
      return '';
    }
    
    const absValue = Math.abs(value);
    const formatted = new Intl.NumberFormat('es-GT', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(absValue);
    
    // Si es negativo, mostrar con paréntesis
    return value < 0 ? `(${formatted})` : formatted;
  }

  formatPercentage(value: number): string {
    if (value === 0 || value === null || value === undefined) {
      return '0.00';
    }
    
    const absValue = Math.abs(value);
    const formatted = new Intl.NumberFormat('es-GT', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(absValue);
    
    // Si es negativo, mostrar con paréntesis
    return value < 0 ? `(${formatted})` : formatted;
  }

  // Getter para obtener los datos correctos (filtrados o originales)
  get datosParaVista(): any {
    return this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
  }
}
