import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { interval } from 'rxjs';
import { BaseComponent } from '@shared/base/base.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-whatsapp',
    templateUrl: './whatsapp.component.html',
    styleUrls: ['./whatsapp.component.scss'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TooltipModule],
    
})
export class WhatsAppComponent extends BaseComponent implements OnInit {

  public stats: any = null;
  public executiveSummary: any = null;
  public sessions: any = {
    data: [],
    total: 0,
    last_page: 1,
    current_page: 1
  };
  public loading: boolean = false;
  public refreshing: boolean = false;
  public error: string = '';
  public selectedPeriod: number = 30;

  public filtros: any = {
    search: '',
    buscador: '',
    status: '',
    id_empresa: '',
    id_usuario: '',
    whatsapp_number: '',
    per_page: 15,
    paginate: 10,
    page: 1,
    orden: 'created_at',
    direccion: 'desc',
    inicio: '',
    fin: '',
    con_mensajes: '',
    activa: ''
  };

  public connectionStatus: string = 'unknown';
  public lastUpdate: Date = new Date();
  
  public autoRefreshEnabled: boolean = true;
  public refreshInterval: number = 30;

  public sendingMessage: boolean = false;
  public manualMessage = {
    whatsapp_number: '',
    message: '',
    empresa_id: null
  };

  // Modals
  public modalRef?: BsModalRef;
  public modalRefDescargar?: BsModalRef;

  public empresas: any[] = [];
  public usuarios: any[] = [];

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private modalService: BsModalService
  ) {
    super();
  }

  ngOnInit() {
    this.loadInitialData();
    this.setupAutoRefresh();
  }

  loadInitialData() {
    this.loading = true;

    Promise.all([
      this.loadStats(),
      this.loadExecutiveSummary(),
      this.loadSessions(),
      this.loadUsuarios()
    ]).finally(() => {
      this.loading = false;
    });
  }

  loadAll() {
    this.filtros = {
      search: '',
      buscador: '',
      status: '',
      empresa_id: '',
      id_empresa: '',
      id_usuario: '',
      whatsapp_number: '',
      per_page: 15,
      paginate: 10,
      page: 1,
      orden: 'created_at',
      direccion: 'desc',
      inicio: '',
      fin: '',
      con_mensajes: '',
      activa: ''
    };
    this.loadSessions();
  }

  loadStats(): Promise<void> {
    return new Promise((resolve) => {
      
      this.apiService.getAll('admin/whatsapp/stats')
        .pipe(this.untilDestroyed())
        .subscribe(
        (response) => {
          if (response && response.success !== undefined) {
            if (response.success) {
              this.stats = response.data;
            }
          } else {
            this.stats = response;
          }
          
          this.connectionStatus = this.determineConnectionStatus();
          this.lastUpdate = new Date();
          resolve();
        },
        (error) => {
          console.error('Error cargando estadísticas:', error);
          this.connectionStatus = 'disconnected';
          this.connectionStatus = 'connected';
          resolve();
        }
      );
    });
  }

  loadExecutiveSummary(): Promise<void> {
    return new Promise((resolve) => {
      console.log('📋 Cargando resumen ejecutivo...');
      
      const params = {
        days: this.selectedPeriod
      };

      this.apiService.getAll('admin/whatsapp/executive-summary', params)
        .pipe(this.untilDestroyed())
        .subscribe(
        (response) => {
          if (response && response.success) {
            this.executiveSummary = response.data;
          } else {
            this.executiveSummary = response;
          }
          resolve();
        },
        (error) => {
          console.error('Error cargando resumen ejecutivo:', error);
          this.executiveSummary = null;
          resolve();
        }
      );
    });
  }

  loadSessions(): Promise<void> {
    return new Promise((resolve) => {
      
      this.apiService.getAll('admin/whatsapp/sessions', this.filtros)
        .pipe(this.untilDestroyed())
        .subscribe(
        (response) => {
          if (response && response.success !== undefined) {
            if (response.success) {
              this.sessions = response.data || { data: [], total: 0, last_page: 1 };
            }
          } else {
            this.sessions = response || { data: [], total: 0, last_page: 1 };
          }
          resolve();
        },
        (error) => {
          console.error('Error cargando sesiones:', error);
          this.sessions = { data: [], total: 0, last_page: 1 };
          resolve();
        }
      );
    });
  }

  loadUsuarios(): Promise<void> {
    return new Promise((resolve) => {
  
      
      this.apiService.getAll('usuarios/list')
        .pipe(this.untilDestroyed())
        .subscribe(
        (usuarios) => {
          this.usuarios = usuarios || [];
          resolve();
        },
        (error) => {
          console.error('Error cargando usuarios:', error);
          this.usuarios = [];
          resolve();
        }
      );
    });
  }

  onPeriodChange() {
    console.log('📅 Cambiando período a:', this.selectedPeriod, 'días');
    Promise.all([
      this.loadStats(),
      this.loadExecutiveSummary()
    ]).then(() => {
      console.log('✅ Estadísticas actualizadas');
    });
  }

  getConnectionPercentage(): number {
    if (!this.stats?.sessions?.total || this.stats.sessions.total === 0) return 0;
    return Math.round((this.stats.sessions.connected / this.stats.sessions.total) * 100);
  }

  getIncomingPercentage(): number {
    if (!this.stats?.messages?.total || this.stats.messages.total === 0) return 0;
    return Math.round((this.stats.messages.incoming / this.stats.messages.total) * 100);
  }

  getOutgoingPercentage(): number {
    if (!this.stats?.messages?.total || this.stats.messages.total === 0) return 0;
    return Math.round((this.stats.messages.outgoing / this.stats.messages.total) * 100);
  }

  getPeriodLabel(): string {
    switch (this.selectedPeriod) {
      case 7: return 'Última semana';
      case 30: return 'Último mes';
      case 90: return 'Últimos 3 meses';
      default: return `Últimos ${this.selectedPeriod} días`;
    }
  }

  // Métodos de filtrado y ordenamiento
  filtrarSesiones() {

    this.filtros.search = this.filtros.buscador;
    this.filtros.empresa_id = this.filtros.id_empresa;
    this.loadSessions();
  }

  limpiarFiltros() {
    this.filtros = {
      search: '',
      buscador: '',
      status: '',
      empresa_id: '',
      id_empresa: '',
      id_usuario: '',
      whatsapp_number: '',
      per_page: 15,
      paginate: 10,
      page: 1,
      orden: 'created_at',
      direccion: 'desc',
      inicio: '',
      fin: '',
      con_mensajes: '',
      activa: ''
    };
    this.loadSessions();
  }

  setOrden(campo: string) {
    if (this.filtros.orden === campo) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = campo;
      this.filtros.direccion = 'asc';
    }
    this.filtrarSesiones();
  }

  setPagination(event: any) {
    this.filtros.page = event.page;
    this.loadSessions();
  }

  // Métodos para modales
  openFilter(template: TemplateRef<any>) {
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg'
    });
  }

  desconectarSesion(session: any) {
    Swal.fire({
     title: '¿Está seguro de desconectar esta sesión?',
     text: 'Esta acción no se puede deshacer.',
     icon: 'warning',
     showCancelButton: true,
     confirmButtonColor: '#3085d6',
     cancelButtonColor: '#d33',
     confirmButtonText: 'Sí, desconectar',
     cancelButtonText: 'Cancelar'
    }).then((result) => {
     if (result.isConfirmed) {
       this.apiService.read('admin/whatsapp/sessions/disconnect/',session.id)
         .pipe(this.untilDestroyed())
         .subscribe(
         (response) => {
           this.alertService.success('Sesión desconectada correctamente', 'WhatsApp');
           this.refreshData();
         },
         (error) => {
           this.alertService.error('Error al desconectar sesión');
         }
       );
     }
    });

  }

  conectarSesion(session: any) {

    console.log('🔌 Conectando sesión:', session.id);
    Swal.fire({
      title: '¿Está seguro de conectar esta sesión?',
      text: 'Esta acción no se puede deshacer.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, conectar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.read('admin/whatsapp/sessions/connect/' ,session.id)
          .pipe(this.untilDestroyed())
          .subscribe(
          (response) => {
            this.alertService.success('Sesión conectada correctamente', 'WhatsApp');
            this.refreshData();
          },
          (error) => {
            this.alertService.error('Error al conectar sesión');
          }
        );
      }
    });
    
  }


  eliminarSesion(session: any) {

    Swal.fire({
      title: '¿Está seguro de eliminar esta sesión?',
      text: 'Esta acción no se puede deshacer.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.delete('admin/whatsapp/sessions', session.id)
          .pipe(this.untilDestroyed())
          .subscribe(
          (response) => {
            this.alertService.success('Sesión eliminada correctamente', 'WhatsApp');
            this.refreshData();
          },
          (error) => {
            this.alertService.error('Error al eliminar sesión');
          }
        );
      }
    });
  }

  setupAutoRefresh() {
    if (this.autoRefreshEnabled) {
      interval(this.refreshInterval * 1000)
        .pipe(this.untilDestroyed())
        .subscribe(() => {
        this.refreshData();
      });
    }
  }

  refreshData() {
    console.log('🔄 Refrescando datos...');
    this.refreshing = true;
    Promise.all([
      this.loadStats(),
      this.loadSessions()
    ]).finally(() => {
      this.refreshing = false;
      console.log('✅ Datos refrescados');
    });
  }

  toggleAutoRefresh() {
    this.autoRefreshEnabled = !this.autoRefreshEnabled;

    if (this.autoRefreshEnabled) {
      this.setupAutoRefresh();
      this.alertService.success('Auto-actualización activada', 'WhatsApp');
    } else {
      this.alertService.info('Auto-actualización desactivada', 'WhatsApp');
    }
  }

  determineConnectionStatus(): string {
    if (!this.stats) return 'unknown';
    
    if (this.stats.sessions?.connected > 0) {
      return 'connected';
    } else if (this.stats.sessions?.total > 0) {
      return 'disconnected';
    } else {
      return 'unknown';
    }
  }

  getConnectionStatusClass(): string {
    switch (this.connectionStatus) {
      case 'connected': return 'text-success';
      case 'disconnected': return 'text-warning';
      default: return 'text-muted';
    }
  }

  getConnectionStatusIcon(): string {
    switch (this.connectionStatus) {
      case 'connected': return 'fa-check-circle';
      case 'disconnected': return 'fa-exclamation-circle';
      default: return 'fa-question-circle';
    }
  }

  getSessionStatusClass(status: string): string {
    if (!status) return 'text-muted';
    
    switch (status.toLowerCase()) {
      case 'connected': return 'text-success';
      case 'pending_code':
      case 'pending_user': return 'text-warning';
      case 'disconnected': return 'text-danger';
      case 'blocked': return 'text-danger';
      case 'pending_verification': return 'text-danger';
      case 'disconnected': return 'text-danger';
      default: return 'text-muted';
    }
  }

  getSessionStatusIcon(status: string): string {
    if (!status) return 'fa-question-circle';
    
    switch (status.toLowerCase()) {
      case 'connected': return 'fa-check-circle';
      case 'pending_code':
      case 'pending_user': return 'fa-clock';
      case 'disconnected': return 'fa-times-circle';
      case 'blocked': return 'fa-times-circle';
      case 'pending_verification': return 'fa-times-circle';
      case 'disconnected': return 'fa-times-circle';
      default: return 'fa-question-circle';
    }
  }

  formatLastActivity(timestamp: string): string {
    if (!timestamp) return 'Nunca';
    
    try {
      const date = new Date(timestamp);
      const now = new Date();
      const diff = now.getTime() - date.getTime();
      const minutes = Math.floor(diff / 60000);
      
      if (minutes < 1) return 'Ahora mismo';
      if (minutes < 60) return `Hace ${minutes} min`;
      
      const hours = Math.floor(minutes / 60);
      if (hours < 24) return `Hace ${hours}h`;
      
      const days = Math.floor(hours / 24);
      return `Hace ${days} días`;
    } catch (error) {
      return 'Fecha inválida';
    }
  }

  applyFilters() {
    console.log('🔍 Aplicando filtros:', this.filtros);
    this.loadSessions();
  }

  clearFilters() {
    console.log('🧹 Limpiando filtros');
    this.filtros = {
      search: '',
      buscador: '',
      status: '',
      empresa_id: '',
      id_empresa: '',
      id_usuario: '',
      whatsapp_number: '',
      per_page: 15,
      paginate: 10,
      page: 1,
      orden: 'created_at',
      direccion: 'desc',
      inicio: '',
      fin: '',
      con_mensajes: '',
      activa: ''
    };
    this.loadSessions();
  }

  openSendMessageModal(template: TemplateRef<any>) {
    console.log('📝 Abriendo modal de envío');
    this.manualMessage = {
      whatsapp_number: '',
      message: '',
      empresa_id: null
    };
    
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg'
    });
  }

  disconnectSession(sessionId: number) {
    if (!confirm('¿Está seguro de desconectar esta sesión?')) return;

    console.log('🔌 Desconectando sesión:', sessionId);
    
    this.alertService.info('Desconectando sesión...', 'WhatsApp');
    this.refreshing = true;
    this.loading = true;
    
    this.apiService.delete('admin/whatsapp/sessions', sessionId)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response) => {
        this.alertService.success('Sesión desconectada correctamente', 'WhatsApp');
        this.refreshData();
      },
      (error) => {
        this.alertService.error('Error al desconectar sesión');
      }
    );
  }

  trackBySessionId(index: number, session: any): number {
    return session?.id || index;
  }

  getMessageTypePercentage(type: 'incoming' | 'outgoing'): number {
    if (!this.stats?.messages?.total) return 0;
    
    const total = this.stats.messages.total;
    const count = this.stats.messages[type] || 0;
    
    return Math.round((count / total) * 100);
  }

  copyToClipboard(text: string): void {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(() => {
        this.alertService.success('Copiado al portapapeles', 'WhatsApp');
      }).catch(err => {
        console.error('Error copiando al portapapeles:', err);
        this.fallbackCopyTextToClipboard(text);
      });
    } else {
      this.fallbackCopyTextToClipboard(text);
    }
  }

  private fallbackCopyTextToClipboard(text: string): void {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.position = 'fixed';
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
      const successful = document.execCommand('copy');
      if (successful) {
        this.alertService.success('Copiado al portapapeles', 'WhatsApp');
      } else {
        this.alertService.error('No se pudo copiar al portapapeles');
      }
    } catch (err) {
      console.error('Fallback: Error copiando al portapapeles:', err);
      this.alertService.error('No se pudo copiar al portapapeles');
    }
    
    document.body.removeChild(textArea);
  }
}