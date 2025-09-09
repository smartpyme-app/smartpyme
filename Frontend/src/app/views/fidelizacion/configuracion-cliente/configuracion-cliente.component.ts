import { Component, OnInit, TemplateRef, ChangeDetectorRef } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { FidelizacionService } from '@services/fidelizacion.service';
import { 
  TipoClienteEmpresa, 
  TipoClienteBase, 
  CreateTipoClienteRequest,
  UpdateTipoClienteRequest,
  PaginatedResponse,
  ConfiguracionAvanzada,
  ReglaUpgrade
} from '../../../models/fidelizacion.interface';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

@Component({
  selector: 'app-configuracion-cliente',
  templateUrl: './configuracion-cliente.component.html'
})
export class ConfiguracionClienteComponent implements OnInit {

  public tiposCliente: TipoClienteEmpresa[] = [];
  public tiposBase: TipoClienteBase[] = [];
  public loading: boolean = false;
  public showModal: boolean = false;
  public editingTipo: TipoClienteEmpresa | null = null;
  public formData: CreateTipoClienteRequest = {
    nivel: 1,
    puntos_por_dolar: 1.0,
    minimo_canje: 100,
    maximo_canje: 1000,
    expiracion_meses: 12,
    is_default: false,
    configuracion_avanzada: this.getDefaultAdvancedConfig()
  };
  public filtros: any = {
    buscador: '',
    orden: 'nivel',
    direccion: 'asc',
    paginate: 25,
    estado: ''
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
  
  public downloading: boolean = false;

  // Propiedades para gestión de reglas de upgrade
  public showUpgradeRulesModal: boolean = false;
  public showReglaModal: boolean = false;
  public editingRegla: ReglaUpgrade | null = null;
  public currentTipoForRules: TipoClienteEmpresa | null = null;
  public reglaForm: ReglaUpgrade = {
    tipo: 'gasto_total',
    umbral: 0,
    nivel_destino: 2,
    descripcion: '',
    activo: true
  };

  modalRef?: BsModalRef;

  constructor(
    private fidelizacionService: FidelizacionService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.loadTiposCliente();
    this.loadTiposBase();
  }

  /**
   * Cargar tipos de cliente
   */
  loadTiposCliente(): void {
    this.loading = true;
    
    // Preparar parámetros para la consulta
    const params = {
      page: this.pagination.current_page,
      paginate: this.filtros.paginate,
      ...(this.filtros.buscador && { search: this.filtros.buscador }),
      ...(this.filtros.orden && { order: this.filtros.orden }),
      ...(this.filtros.direccion && { direction: this.filtros.direccion }),
      ...(this.filtros.estado && { estado: this.filtros.estado })
    };
    
    this.fidelizacionService.getTiposCliente(params).subscribe({
      next: (response: PaginatedResponse<TipoClienteEmpresa>) => {
        if (response.success && response.data) {
          this.tiposCliente = response.data.data;
          this.pagination = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            per_page: response.data.per_page,
            total: response.data.total,
            from: response.data.from,
            to: response.data.to
          };
        } else {
          this.alertService.error(response.message || 'Error al cargar los tipos de cliente');
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error('Error al cargar los tipos de cliente');
        this.loading = false;
      }
    });
  }

  /**
   * Cargar tipos base
   */
  loadTiposBase(): void {
    this.fidelizacionService.getTiposBase().subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.tiposBase = response.data;
        }
      },
      error: (error) => {
        console.error('Error al cargar tipos base:', error);
      }
    });
  }

  /**
   * Abrir modal para crear nuevo tipo
   */
  openCreateModal(): void {
    this.editingTipo = null;
    this.formData = {
      nivel: 1,
      puntos_por_dolar: 1.0,
      minimo_canje: 100,
      maximo_canje: 1000,
      expiracion_meses: 12,
      is_default: false,
      configuracion_avanzada: this.getDefaultAdvancedConfig()
    };
    this.showModal = true;
  }

  /**
   * Abrir modal para editar tipo
   */
  openEditModal(tipo: TipoClienteEmpresa): void {
    this.editingTipo = tipo;
    this.formData = {
      id_tipo_base: tipo.tipo_base?.id,
      nivel: tipo.nivel,
      nombre_personalizado: tipo.is_personalizado ? tipo.nombre_efectivo : undefined,
      puntos_por_dolar: tipo.puntos_por_dolar,
      minimo_canje: tipo.minimo_canje,
      maximo_canje: tipo.maximo_canje,
      expiracion_meses: tipo.expiracion_meses,
      is_default: tipo.is_default,
      configuracion_avanzada: this.ensureAdvancedConfig(tipo.configuracion_avanzada)
    };
    this.showModal = true;
  }

  /**
   * Cerrar modal
   */
  closeModal(): void {
    this.showModal = false;
    this.editingTipo = null;
  }

  /**
   * Obtener configuración avanzada por defecto
   */
  private getDefaultAdvancedConfig(): ConfiguracionAvanzada {
    return {
      valor_punto: 0.01,
      multiplicador_especial: false,
      descuento_cumpleanos: false,
      upgrade_automatico: {
        habilitado: true,
        reglas: []
      },
      beneficios_exclusivos: {
        descuento_maximo_adicional: 0,
        puntos_bienvenida_anual: 0,
        acceso_eventos_vip: false,
        entrega_express_gratis: false,
        asistente_personal: false
      }
    };
  }

  /**
   * Asegurar que la configuración avanzada esté inicializada
   */
  private ensureAdvancedConfig(config: any): ConfiguracionAvanzada {
    if (!config) {
      return this.getDefaultAdvancedConfig();
    }

    return {
      valor_punto: config.valor_punto || 0.01,
      multiplicador_especial: config.multiplicador_especial || false,
      multiplicador_valor: config.multiplicador_valor,
      descuento_cumpleanos: config.descuento_cumpleanos || false,
      descuento_cumpleanos_porcentaje: config.descuento_cumpleanos_porcentaje,
      acceso_exclusivo: config.acceso_exclusivo || false,
      soporte_prioritario: config.soporte_prioritario || false,
      beneficios_exclusivos: {
        descuento_maximo_adicional: config.beneficios_exclusivos?.descuento_maximo_adicional || 0,
        puntos_bienvenida_anual: config.beneficios_exclusivos?.puntos_bienvenida_anual || 0,
        acceso_eventos_vip: config.beneficios_exclusivos?.acceso_eventos_vip || false,
        entrega_express_gratis: config.beneficios_exclusivos?.entrega_express_gratis || false,
        asistente_personal: config.beneficios_exclusivos?.asistente_personal || false
      },
      upgrade_automatico: {
        habilitado: config.upgrade_automatico?.habilitado || true,
        reglas: config.upgrade_automatico?.reglas || []
      }
    };
  }

  /**
   * Obtener opciones de tipos de reglas
   */
  getTiposReglas(): Array<{value: string, label: string, description: string}> {
    const tipos = [
      { value: 'gasto_total', label: 'Gasto Total', description: 'Basado en el monto total gastado' },
      { value: 'puntos_acumulados', label: 'Puntos Acumulados', description: 'Basado en puntos acumulados' },
      { value: 'compras_periodo', label: 'Compras en Período', description: 'Basado en número de compras en un período' }
    ];
    console.log('Tipos de reglas disponibles:', tipos);
    return tipos;
  }

  /**
   * Obtener niveles disponibles para upgrade
   */
  getNivelesDisponibles(): Array<{value: number, label: string}> {
    const nivelActual = this.currentTipoForRules?.nivel || this.formData.nivel;
    const niveles = [];
    for (let i = nivelActual + 1; i <= 3; i++) {
      niveles.push({
        value: i,
        label: `${i} - ${i === 2 ? 'VIP' : i === 3 ? 'Ultra VIP' : 'Nivel ' + i}`
      });
    }
    return niveles;
  }

  /**
   * Abrir modal para agregar nueva regla
   */
  openAddReglaModal(): void {
    this.editingRegla = null;
    const nivelActual = this.currentTipoForRules?.nivel || this.formData.nivel;
    this.reglaForm = {
      tipo: 'gasto_total',
      umbral: 0,
      nivel_destino: this.getNivelesDisponibles()[0]?.value || nivelActual + 1,
      descripcion: '',
      activo: true
    };
    this.showReglaModal = true;
  }

  /**
   * Abrir modal para editar regla existente
   */
  openEditReglaModal(regla: ReglaUpgrade, index: number): void {
    this.editingRegla = { ...regla };
    this.reglaForm = { 
      tipo: regla.tipo,
      umbral: regla.umbral,
      nivel_destino: regla.nivel_destino,
      periodo_meses: regla.periodo_meses,
      descripcion: regla.descripcion,
      activo: regla.activo
    };
    this.showReglaModal = true;
    console.log('Regla original:', regla);
    console.log('Formulario cargado:', this.reglaForm);
    
    // Forzar detección de cambios después de que se renderice el modal
    setTimeout(() => {
      this.cdr.detectChanges();
      // Forzar actualización del select
      this.forceSelectUpdate();
    }, 100);
  }

  /**
   * Cerrar modal de reglas
   */
  closeReglaModal(): void {
    this.showReglaModal = false;
    this.editingRegla = null;
    this.reglaForm = {
      tipo: 'gasto_total',
      umbral: 0,
      nivel_destino: 2,
      descripcion: '',
      activo: true
    };
  }

  /**
   * Guardar regla de upgrade
   */
  saveRegla(): void {
    if (!this.reglaForm.descripcion.trim()) {
      this.alertService.warning('Error', 'La descripción es requerida');
      return;
    }

    if (this.reglaForm.umbral <= 0) {
      this.alertService.warning('Error', 'El umbral debe ser mayor a 0');
      return;
    }

    if (this.reglaForm.tipo === 'compras_periodo' && (!this.reglaForm.periodo_meses || this.reglaForm.periodo_meses <= 0)) {
      this.alertService.warning('Error', 'El período en meses es requerido para este tipo de regla');
      return;
    }

    const reglas = this.currentTipoForRules?.configuracion_avanzada?.upgrade_automatico?.reglas;
    
    if (this.editingRegla) {
      // Editar regla existente
      if (reglas) {
        const index = reglas.findIndex((r: ReglaUpgrade) => r === this.editingRegla);
        if (index !== -1) {
          reglas[index] = { ...this.reglaForm };
        }
      }
    } else {
      // Agregar nueva regla
      if (reglas) {
        reglas.push({ ...this.reglaForm });
      }
    }

    this.closeReglaModal();
    this.alertService.success('Éxito', 'Regla de upgrade guardada exitosamente');
  }

  /**
   * Eliminar regla de upgrade
   */
  removeRegla(index: number): void {
    if (confirm('¿Está seguro de que desea eliminar esta regla de upgrade?')) {
      const reglas = this.currentTipoForRules?.configuracion_avanzada?.upgrade_automatico?.reglas;
      if (reglas) {
        reglas.splice(index, 1);
        this.alertService.success('Éxito', 'Regla de upgrade eliminada');
      }
    }
  }

  /**
   * Toggle estado de regla
   */
  toggleReglaStatus(index: number): void {
    const reglas = this.currentTipoForRules?.configuracion_avanzada?.upgrade_automatico?.reglas;
    if (reglas && reglas[index]) {
      reglas[index].activo = !reglas[index].activo;
    }
  }

  /**
   * Obtener descripción del tipo de regla
   */
  getTipoReglaDescription(tipo: string): string {
    const tipos = this.getTiposReglas();
    return tipos.find(t => t.value === tipo)?.description || '';
  }

  /**
   * Detectar cambios en el tipo de regla
   */
  onTipoReglaChange(value: string): void {
    this.reglaForm.tipo = value;
    this.cdr.detectChanges(); // Forzar detección de cambios
    console.log('Tipo de regla cambiado a:', value);
    console.log('Formulario actualizado:', this.reglaForm);
  }

  /**
   * TrackBy function para el select de tipos
   */
  trackByTipo(index: number, tipo: any): string {
    return tipo.value;
  }

  /**
   * Forzar actualización del select
   */
  forceSelectUpdate(): void {
    // Crear una nueva referencia del objeto para forzar la detección de cambios
    const currentTipo = this.reglaForm.tipo;
    this.reglaForm.tipo = '';
    setTimeout(() => {
      this.reglaForm.tipo = currentTipo;
      this.cdr.detectChanges();
    }, 10);
  }

  /**
   * Obtener label del tipo de regla
   */
  getTipoReglaLabel(tipo: string): string {
    const tipos = this.getTiposReglas();
    return tipos.find(t => t.value === tipo)?.label || tipo;
  }

  /**
   * Abrir modal de reglas de upgrade
   */
  openUpgradeRulesModal(tipo: TipoClienteEmpresa): void {
    this.currentTipoForRules = tipo;
    this.showUpgradeRulesModal = true;
  }

  /**
   * Cerrar modal de reglas de upgrade
   */
  closeUpgradeRulesModal(): void {
    this.showUpgradeRulesModal = false;
    this.currentTipoForRules = null;
  }

  /**
   * Guardar tipo de cliente
   */
  saveTipoCliente(): void {
    // Validar datos
    const errors = this.fidelizacionService.validatePuntosConfig(this.formData);
    if (errors.length > 0) {
      this.alertService.error(errors.join('<br>'));
      return;
    }

    this.loading = true;

    if (this.editingTipo) {
      // Actualizar
      const updateData: UpdateTipoClienteRequest = {
        ...this.formData,
        activo: this.editingTipo.activo
      };

      this.fidelizacionService.updateTipoCliente(this.editingTipo.id, updateData).subscribe({
        next: (response) => {
          if (response.success) {
            this.alertService.success('success','Tipo de cliente actualizado exitosamente');
            this.loadTiposCliente();
            this.closeModal();
          } else {
            this.alertService.error(response.message || 'Error al actualizar el tipo de cliente');
          }
          this.loading = false;
        },
        error: (error) => {
          this.alertService.error('Error al actualizar el tipo de cliente');
          this.loading = false;
        }
      });
    } else {
      // Crear
      this.fidelizacionService.createTipoCliente(this.formData).subscribe({
        next: (response) => {
          if (response.success) {
            this.alertService.success('success','Tipo de cliente creado exitosamente');
            this.loadTiposCliente();
            this.closeModal();
          } else {
            this.alertService.error(response.message || 'Error al crear el tipo de cliente');
          }
          this.loading = false;
        },
        error: (error) => {
          this.alertService.error('Error al crear el tipo de cliente');
          this.loading = false;
        }
      });
    }
  }

  /**
   * Cambiar estado activo/inactivo
   */
  toggleStatus(tipo: TipoClienteEmpresa): void {
    this.fidelizacionService.toggleStatus(tipo.id).subscribe({
      next: (response) => {
        if (response.success) {
          this.alertService.success('success','Estado actualizado exitosamente');
          this.loadTiposCliente();
        } else {
          this.alertService.error(response.message || 'Error al cambiar el estado');
        }
      },
      error: (error) => {
        this.alertService.error('Error al cambiar el estado');
      }
    });
  }

  /**
   * Establecer como tipo por defecto
   */
  setAsDefault(tipo: TipoClienteEmpresa): void {
    if (confirm(`¿Está seguro de establecer "${tipo.nombre_efectivo}" como tipo por defecto para el nivel ${tipo.nivel}?`)) {
      const updateData: UpdateTipoClienteRequest = {
        id_tipo_base: tipo.tipo_base?.id,
        nivel: tipo.nivel,
        nombre_personalizado: tipo.is_personalizado ? tipo.nombre_efectivo : undefined,
        puntos_por_dolar: tipo.puntos_por_dolar,
        minimo_canje: tipo.minimo_canje,
        maximo_canje: tipo.maximo_canje,
        expiracion_meses: tipo.expiracion_meses,
        is_default: true,
        activo: tipo.activo
      };

      this.fidelizacionService.updateTipoCliente(tipo.id, updateData).subscribe({
        next: (response) => {
          if (response.success) {
            this.alertService.success('success','Tipo de cliente establecido como por defecto exitosamente');
            this.loadTiposCliente();
          } else {
            this.alertService.error(response.message || 'Error al establecer como por defecto');
          }
        },
        error: (error) => {
          this.alertService.error('Error al establecer como por defecto');
        }
      });
    }
  }

  /**
   * Eliminar tipo de cliente
   */
  deleteTipoCliente(tipo: TipoClienteEmpresa): void {
    if (confirm(`¿Está seguro de eliminar el tipo de cliente "${tipo.nombre_efectivo}"?`)) {
      this.fidelizacionService.deleteTipoCliente(tipo.id).subscribe({
        next: (response) => {
          if (response.success) {
            this.alertService.success('success','Tipo de cliente eliminado exitosamente');
            this.loadTiposCliente();
          } else {
            this.alertService.error(response.message || 'Error al eliminar el tipo de cliente');
          }
        },
        error: (error) => {
          this.alertService.error('Error al eliminar el tipo de cliente');
        }
      });
    }
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
   * Verificar si es tipo personalizado
   */
  isPersonalizado(tipo: TipoClienteEmpresa): boolean {
    return tipo.is_personalizado;
  }

  /**
   * Obtener descripción del tipo base
   */
  getTipoBaseDescripcion(tipo: TipoClienteEmpresa): string {
    return tipo.tipo_base?.descripcion || 'Tipo personalizado';
  }

  filtrarTiposCliente(): void {
    this.filtros.buscador = this.filtros.buscador.trim();
    this.filtros.orden = this.filtros.orden.trim();
    this.filtros.direccion = this.filtros.direccion.trim();
    this.pagination.current_page = 1; // Reset a la primera página al filtrar
    this.loadTiposCliente();
  }

  setOrden(columna: string): void {
    this.filtros.orden = columna;
    this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    this.filtrarTiposCliente();
  }

  openFilter(template: TemplateRef<any>): void {
    this.modalRef = this.modalService.show(template);
  }

  /**
   * Descargar tipos de cliente
   */
  descargar(): void {
    this.downloading = true;
    // TODO: Implementar descarga de tipos de cliente
    setTimeout(() => {
      this.downloading = false;
      this.alertService.success('success', 'Descarga completada');
    }, 1000);
  }

  setPagination(pagination: any): void {
    this.filtros.paginate = pagination.pageSize;
    this.pagination.current_page = 1; // Reset a la primera página al cambiar el tamaño
    this.filtrarTiposCliente();
  }

  /**
   * Cambiar página
   */
  changePage(page: number): void {
    if (page >= 1 && page <= this.pagination.last_page) {
      this.pagination.current_page = page;
      this.loadTiposCliente();
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
}
