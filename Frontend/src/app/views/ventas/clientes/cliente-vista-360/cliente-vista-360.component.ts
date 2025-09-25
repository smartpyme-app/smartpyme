import { Component, OnInit, TemplateRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FidelizacionService } from '@services/fidelizacion.service';

@Component({
  selector: 'app-cliente-vista-360',
  templateUrl: './cliente-vista-360.component.html',
  styleUrls: ['./cliente-vista-360.component.css']
})
export class ClienteVista360Component implements OnInit {

  public cliente: any = {};
  public loading: boolean = false;
  public activeTab: string = 'analytics';
  public activeHistoryTab: string = 'transactions';
  public showAddNoteModal: boolean = false;
  public newNote: any = {};
  
  // Inicializar con datos por defecto
  public metrics = {
    clv: 0,
    averagePurchase: 0,
    healthScore: 0,
    recency: 0,
    frequency: 0,
    monetary: 0
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
  public topProducts: any[] = [];
  public transactions: any[] = [];
  public categories: any[] = [];

  public visits = [
    // ... mantener datos estáticos como ejemplo
    {
      icon: '👤',
      type: 'visit',
      title: 'Visita presencial - Reunión comercial',
      date: '18 Jun 2025, 2:30 PM',
      meta: [
        { icon: '🏢', text: 'Oficina del cliente' },
        { icon: '⏱️', text: '45 min' },
        { icon: '👨‍💼', text: 'Jorge Martínez (Ventas)' }
      ],
      note: 'Objetivo: Presentación de nuevos productos y renovación de contrato.\nResultado: Cliente interesado en ampliar inventario. Solicitó cotización para productos premium. Se acordó seguimiento en 1 semana.',
      priority: 'high',
      followUp: '📅 Seguimiento: 25 Jun 2025'
    }
    // ... otros visits
  ];

  public visitStats = {
    total: 12,
    thisMonth: 3,
    lastVisit: '5 días'
  };

  modalRef?: BsModalRef;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private fidelizacionService: FidelizacionService,
    private modalService: BsModalService,
    private route: ActivatedRoute,
    private router: Router
  ) { }

  ngOnInit(): void {
    this.loadCliente();
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
          monetary: parseFloat(data.metrics.monetary) || 0
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

      // Cargar ventas mensuales
      if (data.ventasMensuales && Array.isArray(data.ventasMensuales)) {
        this.monthlySales = data.ventasMensuales.map((venta: any) => ({
          month: venta.month,
          amount: parseFloat(venta.amount) || 0,
          height: parseInt(venta.height) || 0,
          high: venta.high || false
        }));
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
        this.transactions = data.transacciones.map((transaccion: any) => ({
          icon: transaccion.icon || '$',
          type: transaccion.type || 'unknown',
          title: transaccion.title || 'Sin título',
          date: transaccion.date || '',
          reference: transaccion.reference || '',
          amount: parseFloat(transaccion.amount) || 0,
          status: transaccion.status || 'unknown'
        }));
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
      
    } catch (error) {
      console.error('Error procesando datos del cliente:', error);
      this.alertService.error('Error al procesar datos del cliente');
    }
  }

  // Método para recargar datos manualmente
  refreshData(): void {
    this.loadCliente();
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
            frequency: 0, // No viene en el endpoint básico
            monetary: 0   // No viene en el endpoint básico
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
  }

  showHistoryTab(tabName: string): void {
    this.activeHistoryTab = tabName;
  }

  openAddNoteModal(): void {
    this.showAddNoteModal = true;
    this.newNote = {
      tipo: '',
      fecha: new Date().toISOString().split('T')[0],
      hora: '14:30',
      titulo: '',
      responsable: '',
      prioridad: 'medium',
      notas: '',
      seguimiento: ''
    };
  }

  closeAddNoteModal(): void {
    this.showAddNoteModal = false;
    this.newNote = {};
  }

  saveNote(): void {
    if (!this.newNote.titulo || !this.newNote.notas) {
      this.alertService.error('Por favor completa todos los campos obligatorios');
      return;
    }

    // Implementar guardado real cuando tengas el endpoint
    this.alertService.success('Nota guardada exitosamente', 'success');
    this.closeAddNoteModal();
  }

  contactWhatsApp(): void {
    const telefono = this.cliente?.telefono || this.cliente?.contactos?.[0]?.telefono;
    if (telefono) {
      const phoneNumber = telefono.replace(/\D/g, '');
      window.open(`https://wa.me/${phoneNumber}`, '_blank');
    } else {
      this.alertService.warning('No se encontró número de teléfono', 'warning');
    }
  }

  sendEmail(): void {
    const correo = this.cliente?.correo || this.cliente?.contactos?.[0]?.correo;
    if (correo) {
      window.open(`mailto:${correo}`, '_blank');
    } else {
      this.alertService.warning('No se encontró dirección de correo', 'warning');
    }
  }

  viewLocation(): void {
    if (this.cliente?.direccion) {
      const encodedAddress = encodeURIComponent(this.cliente.direccion);
      window.open(`https://maps.google.com/?q=${encodedAddress}`, '_blank');
    } else {
      this.alertService.warning('No se encontró dirección', 'warning');
    }
  }

  editCliente(): void {
    if (this.cliente?.id) {
      this.router.navigate(['/cliente/editar/', this.cliente.id]);
    }
  }

  generateReport(): void {
    this.alertService.info('Generando reporte del cliente...', 'info');
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

  formatCurrency(value: number): string {
    if (typeof value !== 'number' || isNaN(value)) return '$0.00';
    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: 'USD'
    }).format(value);
  }

  formatDate(dateString: string): string {
    if (!dateString) return '';
    try {
      const date = new Date(dateString);
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

  get clientePhone(): string {
    return this.cliente?.telefono || this.cliente?.contactos?.[0]?.telefono || '';
  }

  get clienteEmail(): string {
    return this.cliente?.correo || this.cliente?.contactos?.[0]?.correo || '';
  }
}