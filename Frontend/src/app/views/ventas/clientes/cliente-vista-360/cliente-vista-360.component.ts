import { Component, OnInit, TemplateRef, AfterViewInit, ChangeDetectorRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FidelizacionService } from '@services/fidelizacion.service';

declare var bootstrap: any;

@Component({
  selector: 'app-cliente-vista-360',
  templateUrl: './cliente-vista-360.component.html',
  styleUrls: ['./cliente-vista-360.component.css']
})
export class ClienteVista360Component implements OnInit, AfterViewInit {

  public cliente: any = {};
  public loading: boolean = false;
  public activeTab: string = 'analytics';
  public activeHistoryTab: string = 'transactions';
  public noteForm!: FormGroup;
  
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

  public visits: any[] = [];
  public notas: any[] = [];
  public allInteractions: any[] = []; // Lista combinada de visitas y notas
  public usuarios: any[] = []; // Lista de usuarios de la empresa
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

  // Variables para edición de notas
  public isEditingNote: boolean = false;
  public editingNoteId: number | null = null;
  public editingNoteType: string = '';

  // Variables para manejo de errores en el modal
  public modalErrors: any = {};
  public showModalErrors: boolean = false;

  modalRef?: BsModalRef;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private fidelizacionService: FidelizacionService,
    private modalService: BsModalService,
    private route: ActivatedRoute,
    private router: Router,
    private formBuilder: FormBuilder,
    private cdr: ChangeDetectorRef
  ) { 
    this.initializeNoteForm();
  }

  ngOnInit(): void {
    this.combineInteractions();
    
    this.loadCliente();
    this.loadUsuarios();
  }

  ngAfterViewInit(): void {
    // Inicializar tooltips de Bootstrap
    this.initializeTooltips();
  }

  initializeTooltips(): void {
    // Esperar un poco para que el DOM esté completamente renderizado
    setTimeout(() => {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }, 100);
  }

  initializeNoteForm(): void {
    this.noteForm = this.formBuilder.group({
      tipo: ['', Validators.required],
      fecha: [new Date().toISOString().split('T')[0], Validators.required],
      hora: ['14:30', Validators.required],
      titulo: ['', [Validators.required, Validators.minLength(5)]],
      responsable: ['', Validators.required],
      prioridad: ['medium', Validators.required],
      estado: ['activo', Validators.required],
      requiere_seguimiento: [false],
      notas: ['', [Validators.required, Validators.minLength(10)]],
      seguimiento: [''],
      resolucion: ['']
    });
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

  loadUsuarios(): void {
    this.apiService.getAll('usuarios/list').subscribe({
      next: (response) => {
        if (response && Array.isArray(response)) {
          this.usuarios = response;
        } else {
          this.usuarios = [];
        }
      },
      error: (error) => {
        console.error('Error cargando usuarios:', error);
        this.usuarios = [];
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

  openAddNoteModal(template: TemplateRef<any>): void {
    console.log('Abriendo modal de agregar nota...');
    this.isEditingNote = false;
    this.editingNoteId = null;
    this.editingNoteType = '';
    
    // Limpiar errores del modal
    this.modalErrors = {};
    this.showModalErrors = false;
    
    this.noteForm.reset({
      tipo: '',
      fecha: new Date().toISOString().split('T')[0],
      hora: new Date().toTimeString().slice(0, 5),
      titulo: '',
      responsable: '',
      prioridad: 'medium',
      notas: '',
      seguimiento: ''
    });
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static'
    });
  }

  closeAddNoteModal(): void {
    this.modalRef?.hide();
    this.noteForm.reset();
    this.isEditingNote = false;
    this.editingNoteId = null;
    this.editingNoteType = '';
    
    // Limpiar errores del modal
    this.modalErrors = {};
    this.showModalErrors = false;
  }


  editNote(interaction: any, template: TemplateRef<any>): void {
    this.isEditingNote = true;
    this.editingNoteId = interaction.id;
    this.editingNoteType = interaction.type;
    
    // Limpiar errores del modal
    this.modalErrors = {};
    this.showModalErrors = false;
    
    // Mapear los datos de la interacción al formulario
    const formData = {
      tipo: interaction.type === 'visita' ? 'visita' : interaction.tipo,
      fecha: interaction.fecha,
      hora: interaction.hora,
      titulo: interaction.titulo,
      responsable: interaction.responsable,
      prioridad: interaction.prioridad,
      notas: interaction.type === 'visita' ? interaction.descripcion : interaction.contenido,
      seguimiento: interaction.fecha_seguimiento || ''
    };
    
    this.noteForm.patchValue(formData);
    
    // Abrir el modal
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static'
    });
  }

  saveNote(): void {
    // Limpiar errores previos
    this.modalErrors = {};
    this.showModalErrors = false;
    
    if (this.noteForm.invalid) {
      this.markFormGroupTouched();
      this.modalErrors = { general: 'Por favor completa todos los campos obligatorios correctamente' };
      this.showModalErrors = true;
      this.alertService.error('Por favor completa todos los campos obligatorios correctamente');
      return;
    }

    const clienteId = this.route.snapshot.params['id'];
    const formValue = this.noteForm.value;
    
    if (this.isEditingNote && this.editingNoteId) {
      // Actualizar nota existente
      const updateData = {
        tipo: formValue.tipo,
        titulo: formValue.titulo,
        contenido: formValue.notas,
        responsable: formValue.responsable,
        prioridad: formValue.prioridad,
        estado: formValue.estado,
        requiere_seguimiento: formValue.requiere_seguimiento,
        fecha_interaccion: formValue.fecha,
        hora_interaccion: formValue.hora,
        fecha_seguimiento: formValue.seguimiento || null,
        resolucion: formValue.resolucion || null
      };

      const endpoint = this.editingNoteType === 'visita' 
        ? 'cliente-notas/visitas' 
        : 'cliente-notas/notas';

      this.apiService.update(endpoint, this.editingNoteId, updateData).subscribe({
        next: (response) => {
          if (response.success) {
            this.alertService.success('success','Nota actualizada exitosamente');
            this.closeAddNoteModal();
            
            // Estrategia más agresiva: recarga completa del componente
            this.forceRefresh();
          } else {
            this.modalErrors = { general: 'Error al actualizar la nota' };
            this.showModalErrors = true;
            this.alertService.error('Error al actualizar la nota');
          }
        },
        error: (error) => {
          console.error('Error actualizando nota:', error);
          this.handleModalError(error);
        }
      });
    } else {
      // Crear nueva nota
      const notaData = {
        cliente_id: parseInt(clienteId),
        tipo: formValue.tipo,
        titulo: formValue.titulo,
        contenido: formValue.notas,
        responsable: formValue.responsable,
        prioridad: formValue.prioridad,
        estado: formValue.estado,
        requiere_seguimiento: formValue.requiere_seguimiento,
        fecha_interaccion: formValue.fecha,
        hora_interaccion: formValue.hora,
        fecha_seguimiento: formValue.seguimiento || null,
        resolucion: formValue.resolucion || null
      };

      this.apiService.store('cliente-notas/notas', notaData).subscribe({
        next: (response) => {
          if (response.success) {
            this.alertService.success('success','Nota guardada exitosamente');
            this.closeAddNoteModal();
            // Estrategia más agresiva: recarga completa del componente
            this.forceRefresh();
          } else {
            this.modalErrors = { general: 'Error al guardar la nota' };
            this.showModalErrors = true;
            this.alertService.error('Error al guardar la nota');
          }
        },
        error: (error) => {
          console.error('Error guardando nota:', error);
          this.handleModalError(error);
        }
      });
    }
  }

  private markFormGroupTouched(): void {
    Object.keys(this.noteForm.controls).forEach(key => {
      const control = this.noteForm.get(key);
      control?.markAsTouched();
    });
  }

  private handleModalError(error: any): void {
    console.error('Error en modal:', error);
    
    if (error.status === 422 && error.error && error.error.errors) {
      // Manejar errores de validación del backend
      this.modalErrors = error.error.errors;
      this.showModalErrors = true;
      
      // También mostrar en el sistema global con mensaje específico
      const errorMessages = Object.values(error.error.errors).flat();
      this.alertService.error({
        status: 422,
        error: {
          error: errorMessages.join(', ')
        }
      });
    } else if (error.status === 400 && error.error && error.error.message) {
      // Manejar otros errores del backend
      this.modalErrors = { general: error.error.message };
      this.showModalErrors = true;
      this.alertService.error(error.error.message);
    } else {
      // Error genérico
      this.modalErrors = { general: 'Ha ocurrido un error inesperado. Por favor, intenta nuevamente.' };
      this.showModalErrors = true;
      this.alertService.error('Ha ocurrido un error inesperado. Por favor, intenta nuevamente.');
    }
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

  editCliente(): void {
    if (this.cliente?.id) {
      this.router.navigate(['/cliente/editar/', this.cliente.id]);
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

  get clientePhone(): string {
    return this.cliente?.telefono || this.cliente?.contactos?.[0]?.telefono || '';
  }

  get clienteEmail(): string {
    return this.cliente?.correo || this.cliente?.contactos?.[0]?.correo || '';
  }

  // Métodos para manejar errores del modal
  getErrorFields(): string[] {
    return Object.keys(this.modalErrors).filter(field => field !== 'general');
  }

  getFieldLabel(field: string): string {
    const labels: { [key: string]: string } = {
      'fecha_seguimiento': 'Fecha de Seguimiento',
      'fecha': 'Fecha',
      'hora': 'Hora',
      'titulo': 'Título',
      'tipo': 'Tipo de Interacción',
      'responsable': 'Responsable',
      'prioridad': 'Prioridad',
      'estado': 'Estado',
      'notas': 'Notas',
      'contenido': 'Contenido'
    };
    return labels[field] || field;
  }
}