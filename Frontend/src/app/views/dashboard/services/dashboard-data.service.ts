import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { delay, map } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { DashboardData, ChartConfig, MetricCard, AccountItem, CashFlowData, CashFlowItem, Cuenta30Dias, BudgetMetric } from '../models/chart-config.model';
import { HttpClient } from '@angular/common/http';

@Injectable({
  providedIn: 'root'
})
export class DashboardDataService {
  private metricasFinanzasCache: any = null;

  constructor(
    private apiService: ApiService,
    private http: HttpClient
  ) { }

  // Cargar métricas de finanzas desde JSON
  private cargarMetricasFinanzas(): any {
    if (this.metricasFinanzasCache) {
      return this.metricasFinanzasCache;
    }

    // Valores por defecto si no se puede cargar el JSON (se cargarán desde el JSON)
    this.metricasFinanzasCache = {
      ventasTotalesConIVA: 318147.00,
      gastosTotalesConIVA: 165893.00,
      resultados: 152254.00,
      margen: 47.87,
      analisisVertical: [],
      analisisHorizontal: [],
      ventasMensuales: {},
      gastosMensuales: {},
      costosMensuales: {},
      comprasMensuales: {},
      gastosAdminMensuales: {},
      gastosOperMensuales: {},
      gastosComercMensuales: {},
      gastosFinanMensuales: {},
      ventasGastosConfig: null,
      ventasPorMesConfig: null,
      ventasVsPresupuestoConfig: null,
      ventasVsAnioAnteriorConfig: null,
      gastosPorMesConfig: null,
      gastosVsPresupuestoConfig: null,
      gastosVsAnioAnteriorConfig: null,
      gastosPresupuesto: [],
      gastosAnioAnterior: [],
      metricasVentas: {},
      metricasGastos: {}
    };

    // Intentar cargar desde JSON
    this.http.get<any>('assets/data/metricas-finanzas.json').subscribe({
      next: (data) => {
        this.metricasFinanzasCache = data;
      },
      error: () => {
        // Si falla, usar valores por defecto que ya están en cache
        console.warn('No se pudo cargar metricas-finanzas.json, usando valores por defecto');
      }
    });

    return this.metricasFinanzasCache;
  }

  obtenerDatos(): Observable<DashboardData> {
    // Cargar datos desde JSON que simula la respuesta del backend
    return this.http.get<DashboardData>('assets/data/dashboard-dataset.json').pipe(
      map((datos: DashboardData) => {
        // Generar ventas detalladas dinámicamente si no vienen en el JSON
        if (!datos.ventasDetalladas || datos.ventasDetalladas.length === 0) {
          (datos as any).ventasDetalladas = this.generarVentasDetalladasDummy();
        }
        // Generar detalle de ventas para finanzas si no existe
        if (!(datos as any).detalleVentas || (datos as any).detalleVentas.length === 0) {
          (datos as any).detalleVentas = this.generarDetalleVentasParaFinanzas();
        }
        // Asegurar que los datos de inventario estén disponibles
        this.procesarDatosInventario(datos);
        // Ordenar gráficos de barras de mayor a menor
        this.ordenarGraficosBarras(datos);
        // Ordenar arrays de AccountItem de mayor a menor
        this.ordenarArraysAccountItem(datos);
        return datos;
      }),
      delay(500)
    );
    
  }

  /**
   * Procesa y asegura que los datos de inventario estén disponibles
   */
  private procesarDatosInventario(datos: DashboardData): void {
    // Si no existen los datos de inventario, inicializarlos con valores por defecto
    if (!(datos as any).metricasInventario) {
      (datos as any).metricasInventario = {
        productosEnStock: 0,
        promedioInvertido: 0,
        ventasEsperadas: 0,
        utilidadEsperada: 0
      };
    }
    if (!(datos as any).stockPorCategoriaConfig) {
      (datos as any).stockPorCategoriaConfig = null;
    }
    if (!(datos as any).detalleInventario) {
      (datos as any).detalleInventario = [];
    }
    if (!(datos as any).entradasSalidas) {
      (datos as any).entradasSalidas = {
        productosEnStock: 0,
        entradas: 0,
        salidas: 0,
        utilidadEsperada: 0
      };
    }
    if (!(datos as any).entradasSalidasPorMesConfig) {
      (datos as any).entradasSalidasPorMesConfig = null;
    }
    if (!(datos as any).detalleEntradasSalidas) {
      (datos as any).detalleEntradasSalidas = [];
    }
    if (!(datos as any).ajustes) {
      (datos as any).ajustes = {
        productosEnStock: 0,
        unidadesPerdidas: 0,
        unidadesRecuperadas: 0,
        montoTotalRecuperado: 0
      };
    }
    if (!(datos as any).detalleAjustes) {
      (datos as any).detalleAjustes = [];
    }
  }

  /**
   * Ordena los gráficos de barras de mayor a menor
   * Solo ordena gráficos no temporales (excluye gráficos mensuales que deben mantener orden cronológico)
   */
  private ordenarGraficosBarras(datos: DashboardData): void {
    // Ordenar gráficos de barras simples (una serie) - solo los que no son temporales
    const graficosSimples = [
      'ventasPorVendedorChartConfig',
      'ventasPorFormaPagoConfig',
      'gastosPorCategoriaConfig',
      'gastosPorConceptoConfig'
    ];

    graficosSimples.forEach(key => {
      const config = (datos as any)[key];
      if (config && config.type === 'bar' && Array.isArray(config.data) && Array.isArray(config.labels)) {
        // Crear array de pares [valor, etiqueta]
        const pares: Array<{ valor: number; etiqueta: string }> = config.data.map((valor: number, index: number) => ({
          valor,
          etiqueta: config.labels[index]
        }));
        
        // Ordenar de mayor a menor
        pares.sort((a: { valor: number; etiqueta: string }, b: { valor: number; etiqueta: string }) => b.valor - a.valor);
        
        // Reconstruir arrays ordenados
        config.data = pares.map((p: { valor: number; etiqueta: string }) => p.valor);
        config.labels = pares.map((p: { valor: number; etiqueta: string }) => p.etiqueta);
      }
    });

    // NOTA: Los gráficos mensuales (ventasGastosConfig, ventasVsPresupuestoConfig, etc.)
    // se mantienen en orden cronológico y NO se ordenan
  }

  /**
   * Ordena los arrays de AccountItem de mayor a menor por amount
   */
  private ordenarArraysAccountItem(datos: DashboardData): void {
    const arraysParaOrdenar = [
      'ventasPorCanal',
      'ventasPorCategoria',
      'topProductosVendidos',
      'topClientes',
      'cuentasPorCobrar',
      'cuentasPorPagar',
      'cuentasPorCobrarClientes',
      'cuentasPorPagarProveedores',
      'gastosPorProveedor'
    ];

    arraysParaOrdenar.forEach(key => {
      const array = (datos as any)[key];
      if (Array.isArray(array)) {
        array.sort((a: any, b: any) => {
          const amountA = Math.abs(a.amount || 0);
          const amountB = Math.abs(b.amount || 0);
          return amountB - amountA;
        });
      }
    });
  }

  obtenerDatosPorFiltro(filtros: any): Observable<DashboardData> {
    // TODO: Reemplazar con llamada real a la API con filtros
    // return this.apiService.get('/dashboard/datos', { params: filtros });
    
    // Por ahora retorna los mismos datos de ejemplo
    // En producción, aquí se filtrarían los datos según filtros.seccion, filtros.anio, filtros.sucursal
    return this.obtenerDatos().pipe(
      map((datos: DashboardData) => {
        console.log('DashboardDataService - Datos retornados:', {
          seccion: filtros?.seccion,
          tieneDetalleGastos: !!(datos && (datos as any).detalleGastos),
          cantidadGastos: datos && (datos as any).detalleGastos ? (datos as any).detalleGastos.length : 0,
          keys: datos ? Object.keys(datos) : []
        });
        return datos;
      })
    );
  }

  // Generar datos dummy de ventas detalladas con relaciones
  private generarVentasDetalladasDummy(): any[] {
    const canales = ['Facebook', 'Tienda', 'Instagram', 'Marketplace', 'Whatsapp', 'El Salvador'];
    const vendedores = ['Gaby', 'Paula', 'Soporte', 'Jennifer', 'Gabriela', 'DANIELA'];
    const formasPago = ['Efectivo', 'Tarjeta', 'Transferencia', 'Cheque', 'Otros'];
    const categorias = ['Categoría 3', 'Adidas - Masculino', 'Promoción', 'Planes', 'Implementaciones', 'Categoría 1'];
    const productos = ['Producto C', 'AA2', 'Prueba12', 'Adidas Forum morados', 'SERVICIO DE IMPLEM...', 'Plan Avanzado - Smar...'];
    const clientes = ['Consumidor Final', 'Manufacturas Cavalier Sa D...', 'Cliente Ejemplo 1', 'Cliente Ejemplo 2', 'Cliente Ejemplo 3', '(En blanco)'];
    
    const meses = [
      { nombre: 'enero', dias: 31 },
      { nombre: 'febrero', dias: 28 },
      { nombre: 'marzo', dias: 31 },
      { nombre: 'abril', dias: 30 },
      { nombre: 'mayo', dias: 31 },
      { nombre: 'junio', dias: 30 },
      { nombre: 'julio', dias: 31 },
      { nombre: 'agosto', dias: 31 },
      { nombre: 'septiembre', dias: 30 },
      { nombre: 'octubre', dias: 31 },
      { nombre: 'noviembre', dias: 30 },
      { nombre: 'diciembre', dias: 31 }
    ];

    const ventas: any[] = [];
    let facturaId = 1;

    // Generar ventas distribuidas a lo largo del año con relaciones consistentes
    meses.forEach((mes, mesIndex) => {
      const año = 2024;
      const diasEnMes = mes.dias;
      
      // Generar entre 15-30 ventas por mes
      const numVentas = Math.floor(Math.random() * 16) + 15;
      
      for (let i = 0; i < numVentas; i++) {
        const dia = Math.floor(Math.random() * diasEnMes) + 1;
        const fecha = new Date(año, mesIndex, dia);
        
        // Seleccionar valores relacionados (algunos canales tienen más probabilidad con ciertos vendedores)
        const canalIndex = Math.floor(Math.random() * canales.length);
        const canal = canales[canalIndex];
        
        // Relacionar vendedores con canales (ej: Gaby vende más por Facebook)
        let vendedorIndex;
        if (canal === 'Facebook' && Math.random() > 0.3) {
          vendedorIndex = 0; // Gaby
        } else if (canal === 'Tienda' && Math.random() > 0.4) {
          vendedorIndex = 1; // Paula
        } else {
          vendedorIndex = Math.floor(Math.random() * vendedores.length);
        }
        const vendedor = vendedores[vendedorIndex];
        
        // Relacionar forma de pago con canal (ej: Tienda usa más Efectivo)
        let formaPagoIndex;
        if (canal === 'Tienda' && Math.random() > 0.3) {
          formaPagoIndex = 0; // Efectivo
        } else if (canal === 'Facebook' && Math.random() > 0.4) {
          formaPagoIndex = 2; // Transferencia
        } else {
          formaPagoIndex = Math.floor(Math.random() * formasPago.length);
        }
        const formaPago = formasPago[formaPagoIndex];
        
        // Relacionar categoría con producto
        const categoriaIndex = Math.floor(Math.random() * categorias.length);
        const categoria = categorias[categoriaIndex];
        const productoIndex = Math.min(categoriaIndex, productos.length - 1);
        const producto = productos[productoIndex];
        
        // Seleccionar cliente
        const clienteIndex = Math.floor(Math.random() * clientes.length);
        const cliente = clientes[clienteIndex];
        
        // Generar montos realistas y normalizados (entre 1000 y 4000 para mantener consistencia)
        const montoBase = Math.random() * 3000 + 1000; // Entre 1000 y 4000
        const monto = Math.round(montoBase * 100) / 100;
        const cantidad = Math.floor(Math.random() * 5) + 1;
        const precioUnitario = monto / cantidad;
        const descuento = Math.random() > 0.7 ? Math.round(monto * 0.1 * 100) / 100 : 0;
        const ventasSinIVA = monto / 1.12;
        const costoTotal = ventasSinIVA * 0.5; // 50% de costo
        const utilidad = ventasSinIVA - costoTotal;
        
        ventas.push({
          fecha: fecha.toISOString().split('T')[0],
          cliente: cliente,
          factura: `FAC-${facturaId++}`,
          productos: cantidad,
          monto: monto,
          estado: Math.random() > 0.1 ? 'completada' : 'pendiente',
          // Campos para filtros interactivos
          canal: canal,
          vendedor: vendedor,
          formaPago: formaPago,
          categoria: categoria,
          producto: producto,
          cantidad: cantidad,
          precioUnitario: precioUnitario,
          descuento: descuento,
          ventasSinIVA: ventasSinIVA,
          costoTotal: costoTotal,
          utilidad: utilidad
        });
      }
    });

    // Agregar algunas ventas grandes pero normalizadas para que coincidan con los totales
    // Distribuir la venta grande en múltiples ventas más pequeñas
    const ventaGrandeTotal = 30000.00; // Valor normalizado
    const numVentasGrandes = 5;
    const montoPorVenta = ventaGrandeTotal / numVentasGrandes;
    
    for (let i = 0; i < numVentasGrandes; i++) {
      const dia = 13 + i; // Distribuir en varios días de junio
      ventas.push({
        fecha: `2024-06-${dia.toString().padStart(2, '0')}`,
        cliente: '(En blanco)',
        factura: `FAC-ESPECIAL-${i + 1}`,
        productos: 1,
        monto: montoPorVenta,
        estado: 'completada',
        canal: 'Facebook',
        vendedor: 'Gaby',
        formaPago: 'Efectivo',
        categoria: 'Categoría 3',
        producto: 'Producto C',
        cantidad: 1,
        precioUnitario: montoPorVenta,
        descuento: 0,
        ventasSinIVA: montoPorVenta / 1.12,
        costoTotal: (montoPorVenta / 1.12) * 0.5,
        utilidad: (montoPorVenta / 1.12) * 0.5
      });
    }

    return ventas.sort((a, b) => new Date(a.fecha).getTime() - new Date(b.fecha).getTime());
  }

  // Generar detalle de ventas para finanzas (formato con totalConIVA)
  private generarDetalleVentasParaFinanzas(): any[] {
    const ventasDetalladas = this.generarVentasDetalladasDummy();
    return ventasDetalladas.map(venta => ({
      fecha: venta.fecha,
      cliente: venta.cliente,
      factura: venta.factura,
      totalConIVA: venta.monto || 0,
      totalSinIVA: venta.ventasSinIVA || 0,
      sucursalId: '1' // Por defecto
    }));
  }

  // Generar estado de resultados mensual
  private generarEstadoResultados(): any[] {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    // Cargar valores desde JSON
    const metricas = this.cargarMetricasFinanzas();
    const ventasPorMes = metricas.ventasMensuales || {};
    const costosPorMes = metricas.costosMensuales || {};
    const gastosAdminPorMes = metricas.gastosAdminMensuales || {};
    const gastosOperPorMes = metricas.gastosOperMensuales || {};
    const gastosComercPorMes = metricas.gastosComercMensuales || {};
    const gastosFinanPorMes = metricas.gastosFinanMensuales || {};

    // Calcular utilidad bruta por mes
    const utilidadBrutaPorMes: { [key: string]: number } = {};
    meses.forEach(mes => {
      utilidadBrutaPorMes[mes] = (ventasPorMes[mes] || 0) - (costosPorMes[mes] || 0);
    });

    // Calcular utilidad neta por mes
    const utilidadNetaPorMes: { [key: string]: number } = {};
    meses.forEach(mes => {
      utilidadNetaPorMes[mes] = utilidadBrutaPorMes[mes] 
        - (gastosAdminPorMes[mes] || 0)
        - (gastosOperPorMes[mes] || 0)
        - (gastosComercPorMes[mes] || 0)
        - (gastosFinanPorMes[mes] || 0);
    });

    return [
      {
        nombre: 'Ventas',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: ventasPorMes
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
            valores: costosPorMes
          }
        ],
        valores: costosPorMes
      },
      {
        nombre: 'Utilidad bruta',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: utilidadBrutaPorMes
      },
      {
        nombre: 'Gastos administrativos',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: gastosAdminPorMes
      },
      {
        nombre: 'Gastos operativos',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: gastosOperPorMes
      },
      {
        nombre: 'Gastos comerciales',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: gastosComercPorMes
      },
      {
        nombre: 'Gastos financieros',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        valores: gastosFinanPorMes
      },
      {
        nombre: 'Utilidad neta',
        nivel: 0,
        expandido: false,
        tieneHijos: true,
        hijos: [],
        esUtilidadNeta: true,
        valores: utilidadNetaPorMes
      }
    ];
  }

  // Generar flujo de efectivo
  private generarFlujoEfectivo(): any[] {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    // Cargar valores desde JSON
    const metricas = this.cargarMetricasFinanzas();
    const ventasPorMes = metricas.ventasMensuales || {};
    const comprasPorMes = metricas.comprasMensuales || {};
    const gastosPorMes = metricas.gastosMensuales || {};

    // Calcular efectivo disponible (ventas - compras - gastos)
    const efectivoDisponiblePorMes: { [key: string]: number } = {};
    meses.forEach(mes => {
      efectivoDisponiblePorMes[mes] = (ventasPorMes[mes] || 0) - (comprasPorMes[mes] || 0) - (gastosPorMes[mes] || 0);
    });

    return [
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
  }
}

