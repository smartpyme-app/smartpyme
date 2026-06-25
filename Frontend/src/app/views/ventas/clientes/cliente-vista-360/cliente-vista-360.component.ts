import { Component, OnInit, AfterViewInit, ChangeDetectorRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ActivatedRoute, Router } from '@angular/router';
import { BuscadorClienteVista360Component } from './buscador-cliente-vista360/buscador-cliente-vista360.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { FidelizacionService } from '@services/fidelizacion.service';
import { ClienteNotaModalComponent } from '@shared/modals/cliente-nota-modal/cliente-nota-modal.component';
import { ChartConfig } from '@views/dashboard/models/chart-config.model';

declare var bootstrap: any;

@Component({
  selector: 'app-cliente-vista-360',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    ClienteNotaModalComponent,
    BuscadorClienteVista360Component,
  ],
  templateUrl: './cliente-vista-360.component.html',
  styleUrls: ['./cliente-vista-360.component.css']
})
export class ClienteVista360Component implements OnInit, AfterViewInit {

  public cliente: any = {};
  public loading: boolean = false;
  public backUrl = '/fidelizacion/clientes';
  private currentClienteId: string | null = null;
  private resolvingDefaultCliente = false;
  public activeTab: string = 'analytics';
  public activeHistoryTab: string = 'transactions';

  // Inicializar con datos por defecto
  public metrics = {
    clv: 0,
    averagePurchase: 0,
    healthScore: 0,
    recency: 0,
    frequency: 0,
    monetary: 0,
    clasificacion_abc: '',
    tendencia_consumo: '',
    porcentaje_tendencia: 0,
    recency_score: 0,
    frequency_score: 0,
    monetary_score: 0
  };

  public loyaltyPoints = {
    balance: 0,
    redeemed: 0,
    saved: 0,
    puntosDisponibles: 0,
    puntosTotalesGanados: 0,
    puntosTotalesCanjeados: 0,
    fechaUltimaActividad: null,
    tasaRedencion: 0
  };

  public monthlySales: any[] = [];
  public allMonthlySales: any[] = [];
  public salesChartConfig: ChartConfig | null = null;
  public salesChartMonths = 12;
  public readonly salesChartPeriodOptions = [3, 6, 12];
  public topProducts: any[] = [];
  public transactions: any[] = [];
  public categories: any[] = [];

  public visits: any[] = [];
  public notas: any[] = [];
  public allInteractions: any[] = []; // Lista combinada de visitas y notas
  public visitStats = {
    total: 1,
    thisMonth: 1,
    lastVisit: 'Hoy'
  };
  public notasStats = {
    total: 1,
    thisMonth: 1,
    pendientes: 0,
    altaPrioridad: 1
  };

  @ViewChild('clienteNotaModal') clienteNotaModal!: ClienteNotaModalComponent;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private fidelizacionService: FidelizacionService,
    private route: ActivatedRoute,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    this.combineInteractions();

    this.route.queryParamMap.subscribe(query => {
      const returnUrl = query.get('returnUrl');
      if (returnUrl && this.isAllowedBackUrl(returnUrl)) {
        this.backUrl = returnUrl;
      }
    });

    this.route.paramMap.subscribe(params => {
      const clienteId = params.get('id');
      if (!clienteId) {
        if (this.currentClienteId) {
          this.clearClienteState();
        }
        if (!this.resolvingDefaultCliente) {
          this.resolvingDefaultCliente = true;
          this.loadClienteConMasPuntos();
        }
        return;
      }
      this.resolvingDefaultCliente = false;
      if (clienteId !== this.currentClienteId) {
        this.currentClienteId = clienteId;
        this.loadCliente();
      }
    });
  }

  private loadClienteConMasPuntos(): void {
    this.loading = true;
    this.apiService.getAll('cliente-360/top-puntos').subscribe({
      next: (response) => {
        const idCliente = response?.success ? response?.data?.id_cliente : null;
        if (idCliente) {
          this.router.navigate(['/cliente/vista-360', idCliente], {
            queryParams: this.route.snapshot.queryParams,
            replaceUrl: true
          });
          return;
        }
        this.resolvingDefaultCliente = false;
        this.loading = false;
        this.alertService.warning('warning', 'No se encontró un cliente con puntos en la empresa');
      },
      error: (error) => {
        console.error('Error al obtener cliente con más puntos:', error);
        this.resolvingDefaultCliente = false;
        this.loading = false;
        if (error?.status === 404) {
          this.alertService.warning('warning', 'No se encontró un cliente con puntos en la empresa');
        } else {
          this.alertService.error('Error al cargar el cliente destacado');
        }
      }
    });
  }

  ngAfterViewInit(): void {
    // Inicializar tooltips de Bootstrap
    this.initializeTooltips();
  }

  initializeTooltips(): void {
    setTimeout(() => {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach((tooltipTriggerEl: HTMLElement) => {
        const existing = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
        if (existing) {
          existing.dispose();
        }
        new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }, 100);
  }

  setSalesChartMonths(months: number): void {
    if (!this.salesChartPeriodOptions.includes(months) || this.salesChartMonths === months) {
      return;
    }
    this.salesChartMonths = months;
    this.updateMonthlySalesDisplay();
  }

  private readonly salesMonthNames: Record<string, string> = {
    Ene: 'Enero',
    Feb: 'Febrero',
    Mar: 'Marzo',
    Abr: 'Abril',
    May: 'Mayo',
    Jun: 'Junio',
    Jul: 'Julio',
    Ago: 'Agosto',
    Sep: 'Septiembre',
    Oct: 'Octubre',
    Nov: 'Noviembre',
    Dic: 'Diciembre'
  };

  getSalesChartMonthLabel(month: string): string {
    return this.salesMonthNames[month] || month;
  }

  private mapMonthlySalesFromApi(ventas: any[]): any[] {
    return ventas.map((venta: any) => ({
      month: venta.month,
      amount: parseFloat(venta.amount) || 0,
      height: parseInt(venta.height, 10) || 0,
      high: venta.high || false
    }));
  }

  private updateMonthlySalesDisplay(): void {
    if (!this.allMonthlySales.length) {
      this.monthlySales = [];
      this.salesChartConfig = null;
      return;
    }

    const slice = this.allMonthlySales.slice(-this.salesChartMonths);
    this.monthlySales = slice;

    this.salesChartConfig = {
      type: 'bar',
      labels: slice.map(sale =>
        this.salesChartMonths >= 12 ? sale.month : this.getSalesChartMonthLabel(sale.month)
      ),
      tooltipLabels: slice.map(sale => this.getSalesChartMonthLabel(sale.month)),
      data: slice.map(sale => sale.amount),
      colors: ['#3b82f6'],
      rotateLabels: this.salesChartMonths >= 12 ? 45 : 0,
      gridBottom: this.salesChartMonths >= 12 ? '14%' : '8%',
      showBarLabels: false
    };
  }

  loadCliente(): void {
    this.loading = true;
    const clienteId = this.route.snapshot.params['id'];
    
    if (!clienteId) {
      this.alertService.error('ID de cliente no válido');
      this.loading = false;
      return;
    }
    
    this.apiService.getAll(`cliente-360/${clienteId}`).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.processClientData(response.data);
          this.loadNotasYVisitas(clienteId);
        } else {
          this.alertService.error('Error al cargar datos del cliente');
        }
        this.loading = false;
      },
      error: (error) => {
        console.error('Error al cargar cliente:', error);
        
        // Manejo más específico de errores
        if (error.status === 404) {
          this.alertService.error('Cliente no encontrado');
          this.router.navigate(['/clientes']);
        } else if (error.status === 400) {
          this.alertService.error('ID de cliente inválido');
        } else {
          this.alertService.error('Error al cargar datos del cliente');
        }
        
        this.loading = false;
      }
    });
  }

  loadNotasYVisitas(clienteId: string): void {
    let notasLoaded = false;
    let visitasLoaded = false;
    let timeoutId: any = null;
    
    const checkAndCombine = () => {
      if (notasLoaded && visitasLoaded) {
        if (timeoutId) clearTimeout(timeoutId);
        this.combineInteractions();
      }
    };
    
    // Timeout de seguridad para evitar que se quede colgado
    timeoutId = setTimeout(() => {
      this.combineInteractions();
    }, 5000);
    
    // Cargar notas
    this.apiService.getAll(`cliente-notas/notas/${clienteId}`).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.notas = response.data;
        } else {
          this.notas = [];
        }
        notasLoaded = true;
        checkAndCombine();
      },
      error: (error) => {
        console.error('Error cargando notas:', error);
        this.notas = [];
        notasLoaded = true;
        checkAndCombine();
      }
    });

    // Cargar visitas
    this.apiService.getAll(`cliente-notas/visitas/${clienteId}`).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.visits = response.data;
        } else {
          this.visits = [];
        }
        visitasLoaded = true;
        checkAndCombine();
      },
      error: (error) => {
        console.error('Error cargando visitas:', error);
        this.visits = [];
        visitasLoaded = true;
        checkAndCombine();
      }
    });

    // Cargar estadísticas
    this.apiService.getAll(`cliente-notas/estadisticas/${clienteId}`).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.visitStats = {
            total: response.data.total_visitas || 0,
            thisMonth: response.data.visitas_este_mes || 0,
            lastVisit: response.data.ultima_visita ? this.formatDate(response.data.ultima_visita) : 'Nunca'
          };
          this.notasStats = {
            total: response.data.total_notas || 0,
            thisMonth: response.data.notas_este_mes || 0,
            pendientes: response.data.notas_pendientes || 0,
            altaPrioridad: response.data.notas_alta_prioridad || 0
          };
        } else {
          this.visitStats = { total: 0, thisMonth: 0, lastVisit: 'Nunca' };
          this.notasStats = { total: 0, thisMonth: 0, pendientes: 0, altaPrioridad: 0 };
        }
      },
      error: (error) => {
        console.error('Error cargando estadísticas:', error);
        this.visitStats = { total: 0, thisMonth: 0, lastVisit: 'Nunca' };
        this.notasStats = { total: 0, thisMonth: 0, pendientes: 0, altaPrioridad: 0 };
      }
    });
  }

  combineInteractions(): void {
    // Combinar visitas y notas en una sola lista
    const allItems: any[] = [];
    
    // Agregar visitas
    this.visits.forEach(visit => {
      allItems.push({
        ...visit,
        type: 'visita',
        fecha: visit.fecha_visita,
        hora: visit.hora_visita
      });
    });
    
    // Agregar notas
    this.notas.forEach(nota => {
      allItems.push({
        ...nota,
        type: 'nota',
        fecha: nota.fecha_interaccion,
        hora: nota.hora_interaccion
      });
    });
    
    // No agregar datos de prueba - permitir que se muestre el mensaje de "no hay datos"
    // Los datos de prueba solo se usan en desarrollo si es necesario
    
    // Ordenar por fecha (más reciente primero)
    const sortedItems = allItems.sort((a: any, b: any) => {
      const dateA = new Date(a.fecha + ' ' + a.hora);
      const dateB = new Date(b.fecha + ' ' + b.hora);
      return dateB.getTime() - dateA.getTime();
    });
    
    // Forzar nueva asignación para detección de cambios
    this.allInteractions = [...sortedItems];
    
    // Forzar detección de cambios de Angular
    this.cdr.detectChanges();
    
    // Forzar detección de cambios adicional
    setTimeout(() => {
      this.allInteractions = [...this.allInteractions];
      this.cdr.detectChanges();
    }, 50);
  }

  private processClientData(data: any): void {
    try {
      // Cargar datos básicos del cliente
      this.cliente = data.cliente || {};
      
      // Cargar métricas (usando los nombres correctos del servicio)
      if (data.metrics) {
        this.metrics = {
          clv: parseFloat(data.metrics.clv) || parseFloat(data.metrics.totalVentas) || 0,
          averagePurchase: parseFloat(data.metrics.averagePurchase) || 0,
          healthScore: data.metrics.healthScore || 0,
          recency: data.metrics.recency || 0,
          frequency: data.metrics.frequency || 0,
          monetary: parseFloat(data.metrics.monetary) || 0,
          clasificacion_abc: data.metrics.clasificacion_abc || '',
          tendencia_consumo: data.metrics.tendencia_consumo || '',
          porcentaje_tendencia: parseFloat(data.metrics.porcentaje_tendencia) || 0,
          recency_score: data.metrics.recency_score || 0,
          frequency_score: data.metrics.frequency_score || 0,
          monetary_score: data.metrics.monetary_score || 0
        };
      }

      // Cargar datos de fidelización
      if (data.fidelizacion) {
        this.loyaltyPoints = {
          balance: data.fidelizacion.balance || 0,
          redeemed: data.fidelizacion.redeemed || 0,
          saved: data.fidelizacion.saved || 0,
          puntosDisponibles: data.fidelizacion.puntosDisponibles || 0,
          puntosTotalesGanados: data.fidelizacion.puntosTotalesGanados || 0,
          puntosTotalesCanjeados: data.fidelizacion.puntosTotalesCanjeados || 0,
          fechaUltimaActividad: data.fidelizacion.fechaUltimaActividad,
          tasaRedencion: data.fidelizacion.tasaRedencion || 0
        };
      }

      // Cargar ventas mensuales (siempre 12 meses; el filtro 3/6/12 es en frontend)
      if (data.ventasMensuales && Array.isArray(data.ventasMensuales)) {
        this.allMonthlySales = this.mapMonthlySalesFromApi(data.ventasMensuales);
        this.updateMonthlySalesDisplay();
      } else {
        this.allMonthlySales = [];
        this.monthlySales = [];
        this.salesChartConfig = null;
      }

      // Cargar productos top
      if (data.topProducts && Array.isArray(data.topProducts)) {
        this.topProducts = data.topProducts.map((producto: any) => ({
          rank: producto.rank || 0,
          emoji: producto.emoji || '📦',
          name: producto.name || 'Producto sin nombre',
          purchases: producto.purchases || 0,
          lastPurchase: producto.lastPurchase || 'Nunca',
          total: parseFloat(producto.total) || 0
        }));
      }

      // Cargar transacciones
      if (data.transacciones && Array.isArray(data.transacciones)) {
        this.transactions = data.transacciones.map((transaccion: any) => {
          const type = transaccion.type || 'unknown';
          const kindRaw = transaccion.amount_kind ?? transaccion.amountKind;
          const amountKind =
            kindRaw === 'points' ||
            type === 'puntos_ganados' ||
            type === 'puntos_canjeados'
              ? 'points'
              : 'currency';
          return {
            icon: transaccion.icon || '$',
            type,
            title: transaccion.title || 'Sin título',
            date: transaccion.date || '',
            reference: transaccion.reference || '',
            amount: parseFloat(transaccion.amount) || 0,
            amountKind,
            status: (transaccion.status || 'unknown').toString().toLowerCase(),
          };
        });
      }

      // Cargar categorías
      if (data.categorias && Array.isArray(data.categorias)) {
        this.categories = data.categorias.map((categoria: any) => ({
          name: categoria.name || 'Sin nombre',
          percentage: parseFloat(categoria.percentage) || 0,
          total: parseFloat(categoria.total) || 0,
          products: categoria.products || 0,
          purchases: categoria.purchases || 0,
          emoji: categoria.emoji || '📦'
        }));
      }

      setTimeout(() => this.initializeTooltips(), 150);
      
    } catch (error) {
      console.error('Error procesando datos del cliente:', error);
      this.alertService.error('Error al procesar datos del cliente');
    }
  }

  // Método para recargar datos manualmente
  refreshData(): void {
    this.loadCliente();
  }

  // Método para forzar recarga completa
  forceRefresh(): void {
    const clienteId = this.route.snapshot.params['id'];
    
    if (clienteId) {
      this.resetComponentState();
      
      setTimeout(() => {
        this.loadCliente();
      }, 200);
    }
  }

  // Método para cargar solo métricas básicas (más rápido)
  loadQuickMetrics(): void {
    const clienteId = this.route.snapshot.params['id'];
    
    this.apiService.getAll(`cliente-360/${clienteId}/metrics`).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.metrics = {
            clv: response.data.total_gastado || 0,
            averagePurchase: response.data.ticket_promedio || 0,
            healthScore: response.data.health_score || 0,
            recency: response.data.dias_ultima_compra || 0,
            frequency: 0, 
            monetary: 0, 
            clasificacion_abc: response.data.clasificacion_abc || '',
            tendencia_consumo: response.data.tendencia_consumo || '',
            porcentaje_tendencia: parseFloat(response.data.porcentaje_tendencia) || 0,
            recency_score: response.data.recency_score || 0,
            frequency_score: response.data.frequency_score || 0,
            monetary_score: response.data.monetary_score || 0
          };
        }
      },
      error: (error) => {
        console.error('Error cargando métricas:', error);
      }
    });
  }

  showTab(tabName: string): void {
    this.activeTab = tabName;
    // Reinicializar tooltips después del cambio de pestaña
    setTimeout(() => this.initializeTooltips(), 100);
  }

  showHistoryTab(tabName: string): void {
    this.activeHistoryTab = tabName;
    
    // Si es el tab de visitas, asegurar que tenemos datos
    if (tabName === 'visits') {
      this.combineInteractions();
    }
    
    // Reinicializar tooltips después del cambio de pestaña
    setTimeout(() => this.initializeTooltips(), 100);
  }

  openAddNoteModal(): void {
    if (!this.cliente?.id) {
      return;
    }
    this.clienteNotaModal.open(this.cliente.id, this.clienteDisplayName);
  }

  editNote(interaction: any): void {
    if (!this.cliente?.id) {
      return;
    }
    this.clienteNotaModal.openEdit(this.cliente.id, interaction, this.clienteDisplayName);
  }

  onNotaGuardada(): void {
    this.forceRefresh();
  }

  private forceReloadData(clienteId: string): void {
    console.log('Iniciando recarga forzada de datos...');
    
    // Reset completo del estado
    this.resetComponentState();
    
    // Forzar detección de cambios
    setTimeout(() => {
      console.log('Ejecutando recarga después de reset completo...');
      this.loadNotasYVisitas(clienteId);
    }, 150);
  }

  private clearClienteState(): void {
    this.currentClienteId = null;
    this.cliente = {};
    this.loading = false;
    this.resetComponentState();
    this.metrics = {
      clv: 0,
      averagePurchase: 0,
      healthScore: 0,
      recency: 0,
      frequency: 0,
      monetary: 0,
      clasificacion_abc: '',
      tendencia_consumo: '',
      porcentaje_tendencia: 0,
      recency_score: 0,
      frequency_score: 0,
      monetary_score: 0
    };
    this.loyaltyPoints = {
      balance: 0,
      redeemed: 0,
      saved: 0,
      puntosDisponibles: 0,
      puntosTotalesGanados: 0,
      puntosTotalesCanjeados: 0,
      fechaUltimaActividad: null,
      tasaRedencion: 0
    };
    this.monthlySales = [];
    this.allMonthlySales = [];
    this.salesChartConfig = null;
    this.salesChartMonths = 12;
    this.topProducts = [];
    this.transactions = [];
    this.categories = [];
  }

  private resetComponentState(): void {
    // Limpiar todos los arrays
    this.notas = [];
    this.visits = [];
    this.allInteractions = [];
    
    // Reset de estadísticas
    this.visitStats = {
      total: 0,
      thisMonth: 0,
      lastVisit: 'Nunca'
    };
    this.notasStats = {
      total: 0,
      thisMonth: 0,
      pendientes: 0,
      altaPrioridad: 0
    };
    
    // Forzar detección de cambios en todas las propiedades
    this.notas = [...this.notas];
    this.visits = [...this.visits];
    this.allInteractions = [...this.allInteractions];
  }

  private updateLocalInteraction(interactionId: number, updateData: any, type: string): void {
    // Buscar y actualizar en allInteractions
    const index = this.allInteractions.findIndex(item => item.id === interactionId);
    if (index !== -1) {
      // Actualizar los campos modificados
      this.allInteractions[index] = {
        ...this.allInteractions[index],
        titulo: updateData.titulo,
        contenido: updateData.contenido,
        responsable: updateData.responsable,
        prioridad: updateData.prioridad,
        fecha: updateData.fecha_interaccion,
        hora: updateData.hora_interaccion,
        fecha_seguimiento: updateData.fecha_seguimiento
      };
      
      // Forzar detección de cambios
      this.allInteractions = [...this.allInteractions];
    }
  }

  contactWhatsApp(): void {
    const telefono = this.cliente?.telefono || this.cliente?.contactos?.[0]?.telefono;
    if (telefono) {
      const phoneNumber = telefono.replace(/\D/g, '');
      window.open(`https://wa.me/${phoneNumber}`, '_blank');
    } else {
      this.alertService.warning('warning','No se encontró número de teléfono');
    }
  }

  sendEmail(): void {
    const correo = this.cliente?.correo || this.cliente?.contactos?.[0]?.correo;
    if (correo) {
      window.open(`mailto:${correo}`, '_blank');
    } else {
      this.alertService.warning('warning','No se encontró dirección de correo');
    }
  }

  viewLocation(): void {
    if (this.cliente?.direccion) {
      const encodedAddress = encodeURIComponent(this.cliente.direccion);
      window.open(`https://maps.google.com/?q=${encodedAddress}`, '_blank');
    } else {
      this.alertService.warning('warning','No se encontró dirección');
    }
  }

  goBack(): void {
    this.router.navigateByUrl(this.backUrl);
  }

  private isAllowedBackUrl(url: string): boolean {
    return url.startsWith('/fidelizacion/clientes');
  }

  editCliente(): void {
    if (this.cliente?.id) {
      this.router.navigate(['/cliente/editar', this.cliente.id], {
        queryParams: { returnUrl: `/cliente/vista-360/${this.cliente.id}` }
      });
    }
  }

  generateReport(): void {
    this.alertService.info('info','Generando reporte del cliente...');
    // Implementar generación de reporte
  }

  managePoints(): void {
    if (this.cliente?.id) {
      this.router.navigate(['/fidelizacion/cliente-detalles', this.cliente.id]);
    }
  }

  viewInvoices(): void {
    if (this.cliente?.id) {
      this.router.navigate(['/clientes/cliente', this.cliente.id, 'ventas']);
    }
  }

  formatNumber(value: number): string {
    if (typeof value !== 'number' || isNaN(value)) return '0';
    return new Intl.NumberFormat('es-ES', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2
    }).format(value);
  }

  /** Texto del estado en historial (evita mostrar "Descuento" para todo). */
  transactionStatusLabel(transaction: { status: string; type?: string }): string {
    const s = (transaction.status || '').toLowerCase();
    if (s === 'completado' || s === 'completed') {
      return 'Completada';
    }
    if (s === 'earned') {
      return 'Puntos acumulados';
    }
    if (s === 'redeemed') {
      return 'Canje de puntos';
    }
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '—';
  }

  /** Muestra puntos sin símbolo de moneda (canjes vienen negativos en BD). */
  formatPuntosHistorial(transaction: { amount: number; type?: string }): string {
    const raw = Number(transaction.amount);
    if (isNaN(raw)) {
      return '0 pts';
    }
    const n = Math.round(Math.abs(raw));
    const formatted = new Intl.NumberFormat('es-GT', { maximumFractionDigits: 0 }).format(n);
    if (transaction.type === 'puntos_canjeados') {
      return `${formatted} pts canjeados`;
    }
    return `${formatted} pts`;
  }

  getAbcColor(clasificacion: string): string {
    if (clasificacion.includes('Clase A')) return '#10b981';
    if (clasificacion.includes('Clase B')) return '#3b82f6';
    if (clasificacion.includes('Clase C')) return '#f59e0b';
    return '#6b7280';
  }

  getTendenciaColor(tendencia: string): string {
    if (tendencia === 'En Crecimiento') return '#10b981';
    if (tendencia === 'Neutro') return '#6b7280';
    if (tendencia === 'En Decrecimiento') return '#ef4444';
    return '#6b7280';
  }

  formatCurrency(value: number): string {
    if (typeof value !== 'number' || isNaN(value)) {
      return formatEmpresaCurrency(0, this.apiService.auth_user()?.empresa);
    }
    return formatEmpresaCurrency(value, this.apiService.auth_user()?.empresa);
  }

  formatDate(dateString: string): string {
    if (!dateString) return '';
    try { 
      let date: Date;
      if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
        date = new Date(dateString + 'T00:00:00');
      } else if (dateString.includes('T') && dateString.includes('Z')) {
        date = new Date(dateString);
      } else {
    
        date = new Date(dateString);
      }
      
      if (isNaN(date.getTime())) {
        return dateString; 
      }
      
      return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch {
      return dateString; // Devolver original si no se puede formatear
    }
  }

  getPriorityClass(priority: string): string {
    const classes = {
      'high': 'priority-tag high',
      'medium': 'priority-tag medium',
      'completed': 'priority-tag completed',
      'resolved': 'priority-tag resolved',
      'positive': 'priority-tag positive'
    };
    return classes[priority as keyof typeof classes] || 'priority-tag medium';
  }

  getPriorityText(priority: string): string {
    const texts = {
      'high': 'Alta Prioridad',
      'medium': 'Información General',
      'completed': 'Completado',
      'resolved': 'Resuelto',
      'positive': 'Feedback Positivo'
    };
    return texts[priority as keyof typeof texts] || 'Media';
  }

  // Getters para el template
  get hasValidData(): boolean {
    return Object.keys(this.cliente).length > 0;
  }

  get clienteName(): string {
    return this.cliente?.nombre || 'Cliente sin nombre';
  }

  get clienteDisplayName(): string {
    if (!this.cliente?.id) {
      return 'Cliente';
    }
    if (this.cliente.tipo === 'Empresa' && this.cliente.nombre_empresa) {
      return this.cliente.nombre_empresa;
    }
    const nombreCompleto = this.cliente.nombre_completo
      || [this.cliente.nombre, this.cliente.apellido].filter(Boolean).join(' ').trim();
    return nombreCompleto || this.cliente.nombre || 'Cliente sin nombre';
  }

  get clienteInitials(): string {
    const name = (this.clienteDisplayName || '').trim();
    if (!name) {
      return '?';
    }
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  }

  onClienteSeleccionado(cliente: any): void {
    if (!cliente?.id || String(cliente.id) === String(this.cliente?.id)) {
      return;
    }
    this.router.navigate(['/cliente/vista-360', cliente.id], {
      queryParams: { returnUrl: this.backUrl }
    });
  }

}