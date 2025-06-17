import { Component, OnInit, OnDestroy, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { interval, Subscription } from 'rxjs';

@Component({
  selector: 'app-whatsapp',
  templateUrl: './whatsapp.component.html',
  styleUrls: ['./whatsapp.component.scss']
})
export class WhatsAppComponent implements OnInit, OnDestroy {

  public stats: any = null;
  public sessions: any = {
    data: [],
    total: 0,
    last_page: 1,
    current_page: 1
  };
  public loading: boolean = false;
  public refreshing: boolean = false;

  // Propiedades para filtros que faltan en el template
  public filtros: any = {
    search: '',
    buscador: '', // Para el template
    status: '',
    id_empresa: '', // Para el template
    id_usuario: '', // Para el template
    whatsapp_number: '', // Para el template
    per_page: 15,
    paginate: 10, // Para el template
    page: 1,
    orden: 'created_at', // Para ordenamiento
    direccion: 'desc', // Para ordenamiento
    inicio: '', // Para filtro de fecha
    fin: '', // Para filtro de fecha
    con_mensajes: '', // Para filtro de mensajes
    activa: '' // Para filtro de sesión activa
  };

  // Estado de conexiones
  public connectionStatus: string = 'unknown';
  public lastUpdate: Date = new Date();
  
  // Auto-refresh
  private autoRefreshSubscription?: Subscription;
  public autoRefreshEnabled: boolean = true;
  public refreshInterval: number = 30; // segundos

  // Envío manual
  public sendingMessage: boolean = false;
  public manualMessage = {
    whatsapp_number: '',
    message: '',
    empresa_id: null
  };

  // Modals
  public modalRef?: BsModalRef;
  public modalRefDescargar?: BsModalRef;

  // Datos
  public empresas: any[] = [];
  public usuarios: any[] = [];

  // Estados de descarga
  public downloadingSesiones: boolean = false;
  public downloadingMensajes: boolean = false;
  public downloadingEstadisticas: boolean = false;

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.loadInitialData();
    this.setupAutoRefresh();
  }

  ngOnDestroy() {
    if (this.autoRefreshSubscription) {
      this.autoRefreshSubscription.unsubscribe();
    }
  }

  loadInitialData() {
    this.loading = true;
    console.log('📥 Cargando datos iniciales...');
    
    Promise.all([
      this.loadStats(),
      this.loadSessions(),
      this.loadEmpresas(),
      this.loadUsuarios()
    ]).finally(() => {
      this.loading = false;
      console.log('✅ Datos iniciales cargados');
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
      console.log('📊 Cargando estadísticas...');
      
      this.apiService.getAll('admin/whatsapp/stats').subscribe(
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

  loadSessions(): Promise<void> {
    return new Promise((resolve) => {
      console.log('👥 Cargando sesiones...');
      
      this.apiService.getAll('admin/whatsapp/sessions', this.filtros).subscribe(
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

  loadEmpresas(): Promise<void> {
    return new Promise((resolve) => {
      console.log('🏢 Cargando empresas...');
      
      this.apiService.getAll('empresas/list').subscribe(
        (empresas) => {
          this.empresas = empresas || [];
          console.log('✅ Empresas cargadas:', this.empresas);
          resolve();
        },
        (error) => {
          console.error('Error cargando empresas:', error);
          this.empresas = [];
          resolve();
        }
      );
    });
  }

  loadUsuarios(): Promise<void> {
    return new Promise((resolve) => {
      console.log('👤 Cargando usuarios...');
      
      this.apiService.getAll('usuarios/list').subscribe(
        (usuarios) => {
          this.usuarios = usuarios || [];
          console.log('✅ Usuarios cargados:', this.usuarios);
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

  // Métodos de filtrado y ordenamiento
  filtrarSesiones() {
    console.log('🔍 Filtrando sesiones:', this.filtros);
    // Sincronizar propiedades de filtros
    this.filtros.search = this.filtros.buscador;
    this.filtros.empresa_id = this.filtros.id_empresa;
    this.loadSessions();
  }

  limpiarFiltros() {
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

  openDescargar(template: TemplateRef<any>) {
    this.modalRefDescargar = this.modalService.show(template, {
      class: 'modal-lg'
    });
  }

  // Métodos de descarga
  descargarSesiones() {
    this.downloadingSesiones = true;
    console.log('📥 Descargando sesiones...');
    
    this.apiService.getAll('admin/whatsapp/sessions/export', this.filtros).subscribe(
      (response) => {
        // Lógica para descargar archivo
        this.alertService.success('Sesiones descargadas correctamente', 'WhatsApp');
        this.downloadingSesiones = false;
      },
      (error) => {
        this.alertService.error('Error al descargar sesiones');
        this.downloadingSesiones = false;
      }
    );
  }

  descargarMensajes() {
    this.downloadingMensajes = true;
    console.log('📥 Descargando mensajes...');
    
    this.apiService.getAll('admin/whatsapp/messages/export', this.filtros).subscribe(
      (response) => {
        this.alertService.success('Mensajes descargados correctamente', 'WhatsApp');
        this.downloadingMensajes = false;
      },
      (error) => {
        this.alertService.error('Error al descargar mensajes');
        this.downloadingMensajes = false;
      }
    );
  }

  descargarEstadisticas() {
    this.downloadingEstadisticas = true;
    console.log('📥 Descargando estadísticas...');
    
    this.apiService.getAll('admin/whatsapp/stats/export', this.filtros).subscribe(
      (response) => {
        this.alertService.success('Estadísticas descargadas correctamente', 'WhatsApp');
        this.downloadingEstadisticas = false;
      },
      (error) => {
        this.alertService.error('Error al descargar estadísticas');
        this.downloadingEstadisticas = false;
      }
    );
  }

  // Métodos de acciones de sesión
  verDetalles(session: any) {
    console.log('👁️ Ver detalles de sesión:', session);
    // Implementar navegación o modal de detalles
  }

  verMensajes(session: any) {
    console.log('💬 Ver mensajes de sesión:', session);
    // Implementar navegación a mensajes
  }

  desconectarSesion(session: any) {
    if (!confirm('¿Está seguro de desconectar esta sesión?')) return;

    console.log('🔌 Desconectando sesión:', session.id);
    
    this.alertService.info('Desconectando sesión...', 'WhatsApp');
    
    this.apiService.delete('admin/whatsapp/sessions', session.id).subscribe(
      (response) => {
        this.alertService.success('Sesión desconectada correctamente', 'WhatsApp');
        this.refreshData();
      },
      (error) => {
        this.alertService.error('Error al desconectar sesión');
      }
    );
  }

  desbloquearSesion(session: any) {
    if (!confirm('¿Está seguro de desbloquear esta sesión?')) return;

    console.log('🔓 Desbloqueando sesión:', session.id);
    
    this.apiService.store('admin/whatsapp/sessions/unblock', { id: session.id }).subscribe(
      (response) => {
        this.alertService.success('Sesión desbloqueada correctamente', 'WhatsApp');
        this.refreshData();
      },
      (error) => {
        this.alertService.error('Error al desbloquear sesión');
      }
    );
  }

  eliminarSesion(session: any) {
    if (!confirm('¿Está seguro de eliminar esta sesión? Esta acción no se puede deshacer.')) return;

    console.log('🗑️ Eliminando sesión:', session.id);
    
    this.apiService.delete('admin/whatsapp/sessions', session.id).subscribe(
      (response) => {
        this.alertService.success('Sesión eliminada correctamente', 'WhatsApp');
        this.refreshData();
      },
      (error) => {
        this.alertService.error('Error al eliminar sesión');
      }
    );
  }

  setupAutoRefresh() {
    if (this.autoRefreshEnabled) {
      this.autoRefreshSubscription = interval(this.refreshInterval * 1000).subscribe(() => {
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
    
    if (this.autoRefreshSubscription) {
      this.autoRefreshSubscription.unsubscribe();
    }

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
    
    this.apiService.delete('admin/whatsapp/sessions', sessionId).subscribe(
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