import { Component, OnInit, OnDestroy, TemplateRef, ChangeDetectorRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { FidelizacionService } from '@services/fidelizacion.service';
import { ApiService } from '@services/api.service';
import { 
  TipoClienteEmpresa, 
  TipoClienteBase, 
  PaginatedResponse
} from '../../../models/fidelizacion.interface';
import { 
  ClienteFidelizacion
} from '../../../services/fidelizacion.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

@Component({
  selector: 'app-clientes-fidelizacion',
  templateUrl: './clientes-fidelizacion.component.html'
})
export class ClientesFidelizacionComponent implements OnInit, OnDestroy {

  public clientes: ClienteFidelizacion[] = [];
  public tiposCliente: TipoClienteEmpresa[] = [];
  public currentTipo: TipoClienteEmpresa | null = null;
  public loading: boolean = false;
  public downloading: boolean = false;
  public showChangeTypeModal: boolean = false;
  public selectedCliente: ClienteFidelizacion | null = null;
  public selectedTipoId: number | null = null;
  private searchTimeout: any = null;

  public filtros: any = {
    buscador: '',
    tipo_cliente: '',
    nivel: '',
    puntos_min: '',
    puntos_max: '',
    estado: '',
    orden: 'nombre',
    direccion: 'asc',
    paginate: 25
  };
  
  // Propiedades para paginación
  public pagination: any = {
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
    from: 0,
    to: 0
  };

  modalRef?: BsModalRef;

  constructor(
    private fidelizacionService: FidelizacionService,
    private apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private cdr: ChangeDetectorRef,
    private route: ActivatedRoute,
    private router: Router
  ) { }

  ngOnInit(): void {
    this.route.params.subscribe(params => {
      if (params['tipoId']) {
        this.loadTipoCliente(params['tipoId']);
      }
      this.loadAll();
    });
  }

  ngOnDestroy(): void {
    // Limpiar timeout al destruir el componente
    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
    }
  }

  loadAll(): void {
    this.loadClientes();
    this.loadTiposCliente();
  }

  /**
   * Cargar tipo de cliente específico
   */
  loadTipoCliente(tipoId: number): void {
    this.fidelizacionService.getTiposCliente({ paginate: 100 }).subscribe({
      next: (response: PaginatedResponse<TipoClienteEmpresa>) => {
        if (response.success && response.data) {
          this.currentTipo = response.data.data.find(t => t.id === parseInt(tipoId.toString())) || null;
        }
      },
      error: (error) => {
        console.error('Error al cargar tipo de cliente:', error);
      }
    });
  }

  /**
   * Cargar clientes con información de lealtad
   */
  loadClientes(): void {
    this.loading = true;
    
    // Preparar parámetros para la consulta
    const params = {
      page: this.pagination.current_page,
      paginate: this.filtros.paginate,
      ...(this.filtros.buscador && { search: this.filtros.buscador }),
      ...(this.filtros.tipo_cliente && { tipo_cliente: this.filtros.tipo_cliente }),
      ...(this.filtros.nivel && { nivel: this.filtros.nivel }),
      ...(this.filtros.puntos_min && { puntos_min: this.filtros.puntos_min }),
      ...(this.filtros.puntos_max && { puntos_max: this.filtros.puntos_max }),
      ...(this.filtros.estado && { estado: this.filtros.estado }),
      ...(this.filtros.orden && { order: this.filtros.orden }),
      ...(this.filtros.direccion && { direction: this.filtros.direccion })
    };
    
    // Determinar qué método usar según si hay un tipo específico
    const routeParams = this.route.snapshot.params;
    const tipoId = routeParams['tipoId'];
    
    const apiCall = tipoId 
      ? this.fidelizacionService.getClientesPorTipo(parseInt(tipoId), params)
      : this.fidelizacionService.getClientesFidelizacion(params);
    
    apiCall.subscribe({
      next: (response: PaginatedResponse<ClienteFidelizacion>) => {
        if (response.success && response.data) {
          this.clientes = response.data.data;
          this.pagination = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            per_page: response.data.per_page,
            total: response.data.total,
            from: response.data.from,
            to: response.data.to
          };
        } else {
          this.alertService.error(response.message || 'Error al cargar los clientes');
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error('Error al cargar los clientes');
        this.loading = false;
      }
    });
  }


  /**
   * Cargar tipos de cliente
   */
  loadTiposCliente(): void {
    this.fidelizacionService.getTiposCliente({ paginate: 100 }).subscribe({
      next: (response: PaginatedResponse<TipoClienteEmpresa>) => {
        if (response.success && response.data) {
          this.tiposCliente = response.data.data;
        }
      },
      error: (error) => {
        console.error('Error al cargar tipos de cliente:', error);
      }
    });
  }

  /**
   * Abrir modal para cambiar tipo de cliente
   */
  openChangeTypeModal(cliente: ClienteFidelizacion): void {
    this.selectedCliente = cliente;
    this.selectedTipoId = cliente.tipo_cliente_fidelizacion?.id || null;
    this.showChangeTypeModal = true;
  }

  /**
   * Cerrar modal de cambio de tipo
   */
  closeChangeTypeModal(): void {
    this.showChangeTypeModal = false;
    this.selectedCliente = null;
    this.selectedTipoId = null;
  }

  /**
   * Cambiar tipo de cliente
   */
  changeClienteType(): void {
    if (!this.selectedCliente || !this.selectedTipoId) {
      this.alertService.warning('Error', 'Debe seleccionar un tipo de cliente');
      return;
    }

    this.loading = true;

    this.fidelizacionService.cambiarTipoCliente(this.selectedCliente.id, this.selectedTipoId).subscribe({
      next: (response) => {
        if (response.success) {
          const nuevoTipo = this.tiposCliente.find(t => t.id === this.selectedTipoId);
          if (nuevoTipo && this.selectedCliente) {
            this.selectedCliente.tipo_cliente_fidelizacion = nuevoTipo;
            this.selectedCliente.nivel_actual = nuevoTipo.nivel;
          }
          
          this.alertService.success('Éxito', 'Tipo de cliente actualizado exitosamente');
          this.closeChangeTypeModal();
        } else {
          this.alertService.error(response.message || 'Error al cambiar el tipo de cliente');
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error('Error al cambiar el tipo de cliente');
        this.loading = false;
      }
    });
  }

  /**
   * Ver detalles del cliente
   */
  viewClienteDetails(cliente: ClienteFidelizacion): void {
    this.router.navigate(['/cliente/detalles', cliente.id]);
  }

  /**
   * Ver detalles de lealtad del cliente
   */
  viewClienteLealtad(cliente: ClienteFidelizacion): void {
    this.router.navigate(['/fidelizacion/cliente-detalles', cliente.id]);
  }

  /**
   * Obtener nombre del nivel
   */
  getNivelNombre(nivel: number): string {
    return this.fidelizacionService.getNivelNombre(nivel);
  }

  /**
   * Obtener clase CSS para el nivel
   */
  getNivelClass(nivel: number): string {
    return this.fidelizacionService.getNivelClass(nivel);
  }

  /**
   * Obtener tipo de cliente actual
   */
  getTipoClienteActual(cliente: ClienteFidelizacion): string {
    if (cliente.tipo_cliente_fidelizacion) {
      return cliente.tipo_cliente_fidelizacion.nombre_efectivo;
    }
    return 'Sin tipo asignado';
  }

  /**
   * Filtrar clientes
   */
  filtrarClientes(): void {
    this.filtros.buscador = this.filtros.buscador.trim();
    this.pagination.current_page = 1; // Reset a la primera página al filtrar
    this.loadClientes();
  }

  /**
   * Manejar input de búsqueda con debounce
   */
  onSearchInput(): void {
    // Limpiar timeout anterior si existe
    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
    }
    
    // Establecer nuevo timeout para búsqueda con debounce
    this.searchTimeout = setTimeout(() => {
      this.filtrarClientes();
    }, 500); // 500ms de delay
  }

  /**
   * Establecer orden
   */
  setOrden(columna: string): void {
    if (this.filtros.orden === columna) {
      // Si es la misma columna, cambiar dirección
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      // Si es una columna diferente, establecer como ascendente por defecto
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.filtrarClientes();
  }

  /**
   * Abrir modal de filtros
   */
  openFilter(template: TemplateRef<any>): void {
    this.modalRef = this.modalService.show(template);
  }

  /**
   * Descargar clientes
   */
  descargar(): void {
    this.downloading = true;
    // TODO: Implementar descarga de clientes con lealtad
    setTimeout(() => {
      this.downloading = false;
      this.alertService.success('Éxito', 'Descarga completada');
    }, 1000);
  }

  /**
   * Establecer paginación
   */
  setPagination(pagination: any): void {
    this.filtros.paginate = pagination.pageSize;
    this.pagination.current_page = 1; // Reset a la primera página al cambiar el tamaño
    this.filtrarClientes();
  }

  /**
   * Cambiar página
   */
  changePage(page: number): void {
    if (page >= 1 && page <= this.pagination.last_page) {
      this.pagination.current_page = page;
      this.loadClientes();
    }
  }

  /**
   * Obtener array de páginas para el paginador
   */
  getPagesArray(): number[] {
    const pages: number[] = [];
    const current = this.pagination.current_page;
    const last = this.pagination.last_page;
    
    // Mostrar máximo 5 páginas
    let start = Math.max(1, current - 2);
    let end = Math.min(last, current + 2);
    
    // Ajustar si estamos cerca del inicio o final
    if (end - start < 4) {
      if (start === 1) {
        end = Math.min(last, start + 4);
      } else {
        start = Math.max(1, end - 4);
      }
    }
    
    for (let i = start; i <= end; i++) {
      pages.push(i);
    }
    
    return pages;
  }

  /**
   * Formatear fecha
   */
  formatDate(dateString: string): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
  }

  /**
   * Formatear número con separadores de miles
   */
  formatNumber(num: number): string {
    if (!num) return '0';
    return num.toLocaleString('es-ES');
  }
}
