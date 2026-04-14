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
import Swal from 'sweetalert2';

@Component({
  selector: 'app-configuracion-cliente',
  templateUrl: './configuracion-cliente.component.html',
  styleUrls: ['./configuracion-cliente.component.css']
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
  /** Valor del selector de nivel en UI: id de tipo base (number) o 'personalizado' */
  public nivelSeleccionado: number | 'personalizado' | null = null;
  public filtros: any = {
    buscador: '',
    orden: 'nivel',
    direccion: 'asc',
    paginate: 25,
    estado: '',
    tipo: ''
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

  /** Ocultar configuración avanzada hasta que esté lista */
  public mostrarConfiguracionAvanzada: boolean = false;

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
    this.filtros.estado = '';
    this.filtros.tipo = '';
    this.pagination.current_page = 1;
    this.loadTiposCliente();
    this.loadTiposBase();
  }

  /**
   * Cargar tipos de cliente
   */
  loadTiposCliente(): void {
    this.loading = true;
    
    // Preparar parámetros para la consulta con cache busting
    const params = {
      page: this.pagination.current_page,
      paginate: this.filtros.paginate,
      _t: Date.now(), // Cache busting timestamp
      ...(this.filtros.buscador && { search: this.filtros.buscador }),
      ...(this.filtros.orden && { order: this.filtros.orden }),
      ...(this.filtros.direccion && { direction: this.filtros.direccion }),
      ...(this.filtros.estado && { estado: this.filtros.estado }),
      ...(this.filtros.tipo && { tipo: this.filtros.tipo })
    };
    
    this.fidelizacionService.getTiposCliente(params).subscribe({
      next: (response: PaginatedResponse<TipoClienteEmpresa>) => {
        if (response.success && response.data) {
          // Formatear los datos para limitar decimales
          this.tiposCliente = response.data.data.map((tipo) => ({
            ...tipo,
            puntos_por_dolar: this.formatDecimal(parseFloat(tipo.puntos_por_dolar.toString())),
            configuracion_avanzada: {
              ...tipo.configuracion_avanzada,
              valor_punto: this.resolveValorPuntoFromTipo(tipo),
            },
          }));
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
   * Forzar recarga completa de datos (útil para evitar problemas de caché)
   */
  private forceReloadData(): void {
    // Limpiar datos actuales
    this.tiposCliente = [];
    this.tiposBase = [];
    
    // Recargar con un pequeño delay para asegurar que el servidor haya procesado los cambios
    setTimeout(() => {
      this.loadAll();
    }, 200);
  }

  /**
   * Abrir modal para crear nuevo tipo
   */
  openCreateModal(): void {
    this.editingTipo = null;
    const primerTipoBase = this.tiposBase?.[0];
    this.formData = {
      id_tipo_base: primerTipoBase?.id,
      nivel: primerTipoBase?.orden ?? 1,
      nombre_personalizado: undefined,
      puntos_por_dolar: 1.0,
      minimo_canje: 100,
      maximo_canje: 1000,
      expiracion_meses: 12,
      is_default: false,
      configuracion_avanzada: this.getDefaultAdvancedConfig()
    };
    this.nivelSeleccionado = primerTipoBase ? primerTipoBase.id : 'personalizado';
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
      puntos_por_dolar: this.formatDecimal(parseFloat(tipo.puntos_por_dolar.toString())),
      minimo_canje: tipo.minimo_canje,
      maximo_canje: tipo.maximo_canje,
      expiracion_meses: tipo.expiracion_meses,
      is_default: tipo.is_default,
      configuracion_avanzada: this.ensureAdvancedConfig({
        ...tipo.configuracion_avanzada,
        valor_punto: this.resolveValorPuntoFromTipo(tipo),
      }),
    };
    this.nivelSeleccionado = tipo.is_personalizado ? 'personalizado' : (tipo.tipo_base?.id ?? null);
    this.showModal = true;
  }

  /**
   * Cerrar modal
   */
  closeModal(): void {
    this.showModal = false;
    this.editingTipo = null;
    this.nivelSeleccionado = null;
  }

  /**
   * Al cambiar el nivel seleccionado en la UI (Standard, VIP, Ultra VIP o Personalizado)
   */
  onNivelSeleccionadoChange(value: number | 'personalizado' | null): void {
    this.nivelSeleccionado = value;
    if (value === 'personalizado') {
      this.formData.id_tipo_base = undefined;
      this.formData.nivel = this.formData.nivel || 1;
      this.formData.nombre_personalizado = this.formData.nombre_personalizado || '';
    } else if (value !== null && typeof value === 'number') {
      const tipoBase = this.tiposBase.find(t => t.id === value);
      if (tipoBase) {
        this.formData.id_tipo_base = tipoBase.id;
        this.formData.nivel = tipoBase.orden;
        this.formData.nombre_personalizado = undefined;
      }
    }
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
      // TODO: BENEFICIOS_EXCLUSIVOS - Mantener estructura para compatibilidad; UI comentada en .html
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
      valor_punto: config.valor_punto ?? 0.01,
      multiplicador_especial: config.multiplicador_especial || false,
      multiplicador_valor: config.multiplicador_valor,
      descuento_cumpleanos: config.descuento_cumpleanos || false,
      descuento_cumpleanos_porcentaje: config.descuento_cumpleanos_porcentaje,
      acceso_exclusivo: config.acceso_exclusivo || false,
      soporte_prioritario: config.soporte_prioritario || false,
      // TODO: BENEFICIOS_EXCLUSIVOS - Mantener para compatibilidad; UI comentada
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

    if (!this.currentTipoForRules) {
      this.alertService.error('No se encontró el tipo de cliente para actualizar');
      return;
    }

    const reglas = this.currentTipoForRules.configuracion_avanzada?.upgrade_automatico?.reglas;
    
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

    // Guardar los cambios en la base de datos
    this.guardarReglasUpgrade();
  }

  /**
   * Guardar las reglas de upgrade en la base de datos
   */
  private guardarReglasUpgrade(): void {
    if (!this.currentTipoForRules) {
      return;
    }

    this.loading = true;

    const updateData: UpdateTipoClienteRequest = {
      id_tipo_base: this.currentTipoForRules.tipo_base?.id,
      nivel: this.currentTipoForRules.nivel,
      nombre_personalizado: this.currentTipoForRules.is_personalizado ? this.currentTipoForRules.nombre_efectivo : undefined,
      puntos_por_dolar: this.currentTipoForRules.puntos_por_dolar,
      minimo_canje: this.currentTipoForRules.minimo_canje,
      maximo_canje: this.currentTipoForRules.maximo_canje,
      expiracion_meses: this.currentTipoForRules.expiracion_meses,
      is_default: this.currentTipoForRules.is_default,
      activo: this.currentTipoForRules.activo,
      configuracion_avanzada: this.currentTipoForRules.configuracion_avanzada
    };

    this.fidelizacionService.updateTipoCliente(this.currentTipoForRules.id, updateData).subscribe({
      next: (response) => {
        if (response.success) {
          // Actualizar el objeto currentTipoForRules con los nuevos datos
          if (response.data) {
            this.currentTipoForRules = response.data;
          }
          this.alertService.success('Éxito', 'Reglas de upgrade guardadas exitosamente');
          this.closeReglaModal();
          
          // Forzar recarga completa para evitar problemas de caché
          this.forceReloadData();
        } else {
          this.alertService.error(response.message || 'Error al guardar las reglas de upgrade');
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error('Error al guardar las reglas de upgrade');
        this.loading = false;
      }
    });
  }

  /**
   * Eliminar regla de upgrade
   */
  removeRegla(index: number): void {
    if (confirm('¿Está seguro de que desea eliminar esta regla de upgrade?')) {
      const reglas = this.currentTipoForRules?.configuracion_avanzada?.upgrade_automatico?.reglas;
      if (reglas) {
        reglas.splice(index, 1);
        // Guardar los cambios en la base de datos
        this.guardarReglasUpgrade();
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
      // Guardar los cambios en la base de datos
      this.guardarReglasUpgrade();
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
   * Mostrar simulación de venta con la configuración actual
   */
  private mostrarSimulacionVenta(): void {
    const valorPunto = this.formData.configuracion_avanzada?.valor_punto ?? 0.01;
    const puntosPorDolar = this.formData.puntos_por_dolar || 1.0;
    const minimoCanje = this.formData.minimo_canje || 100;
    const maximoCanje = this.formData.maximo_canje || 1000;
    
    // Ejemplos de ventas con diferentes montos
    const ejemplosVentas = [50, 100, 250, 500];
    
    const generarEjemploVenta = (monto: number) => {
      const puntosObtenidos = monto * puntosPorDolar;
      const valorPuntosEnDolares = puntosObtenidos * valorPunto;
      const puntosCanje = Math.min(puntosObtenidos, maximoCanje);
      const descuentoCanje = puntosCanje * valorPunto;
      
      return `
        <div style="background: #e8f5e8; padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid #28a745;">
          <h6 style="color: #28a745; margin-bottom: 8px; font-weight: 600;">🛒 Venta: $${monto}</h6>
          <div style="font-size: 13px;">
            <div><strong>Puntos obtenidos:</strong> ${puntosObtenidos.toFixed(0)} puntos</div>
            <div><strong>Valor de puntos:</strong> $${valorPuntosEnDolares.toFixed(2)}</div>
            <div><strong>Descuento máximo:</strong> $${descuentoCanje.toFixed(2)}</div>
          </div>
        </div>
      `;
    };
    
    const ejemplosHtml = ejemplosVentas.map(monto => generarEjemploVenta(monto)).join('');
    
    const htmlContent = `
      <div style="text-align: left; font-family: Arial, sans-serif;">
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #dee2e6;">
          <h5 style="color: #495057; margin-bottom: 10px;">💰 Configuración Actual:</h5>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
            <div><strong>Valor del punto:</strong> $${valorPunto.toFixed(4)}</div>
            <div><strong>Puntos por dólar:</strong> ${puntosPorDolar}</div>
            <div><strong>Mínimo canje:</strong> ${minimoCanje} puntos</div>
            <div><strong>Máximo canje:</strong> ${maximoCanje} puntos</div>
          </div>
        </div>
        
        <div style="margin-bottom: 15px;">
          <h5 style="color: #495057; margin-bottom: 10px;">📈 Ejemplos de Ventas:</h5>
          ${ejemplosHtml}
        </div>
        
        <div style="margin-top: 15px; padding: 10px; background: #d1ecf1; border-radius: 6px; border-left: 4px solid #17a2b8;">
          <small style="color: #0c5460; display: block; margin-bottom: 5px;">
            <strong>💡 Nota:</strong> Esta es una simulación basada en la configuración actual.
          </small>
          <small style="color: #0c5460;">
            Los valores reales pueden variar según las reglas específicas del negocio y promociones activas.
          </small>
        </div>
      </div>
    `;

    Swal.fire({
      title: 'Simulación de Ventas',
      html: htmlContent,
      width: '700px',
      showCancelButton: true,
      confirmButtonText: 'Aceptar y Guardar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      reverseButtons: true,
      customClass: {
        popup: 'swal2-popup-custom',
        htmlContainer: 'swal2-html-container-custom'
      }
    }).then((result) => {
      if (result.isConfirmed) {
        this.procederConGuardado();
      }
    });
  }

  /**
   * Proceder con el guardado después de mostrar la simulación
   */
  private procederConGuardado(): void {
    // Validar nombre personalizado cuando es tipo personalizado
    if (this.nivelSeleccionado === 'personalizado') {
      if (!this.formData.nombre_personalizado?.trim()) {
        this.alertService.error('El nombre del tipo es requerido para tipos personalizados');
        return;
      }
    }
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

      console.log('Datos a enviar para actualización:', updateData);
      console.log('Configuración avanzada a enviar:', updateData.configuracion_avanzada);

      this.fidelizacionService.updateTipoCliente(this.editingTipo.id, updateData).subscribe({
        next: (response) => {
          console.log('Respuesta del servidor:', response);
          if (response.success) {
            // Actualizar el objeto editingTipo con los nuevos datos
            if (response.data) {
              this.editingTipo = response.data;
            }
            this.alertService.success('success','Tipo de cliente actualizado exitosamente');
            
            // Forzar recarga completa para evitar problemas de caché
            this.forceReloadData();
            
            this.closeModal();
          } else {
            this.alertService.error(response.message || 'Error al actualizar el tipo de cliente');
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Error al actualizar:', error);
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
   * Guardar tipo de cliente
   */
  saveTipoCliente(): void {
    // Debug: Mostrar el estado actual del formulario
    console.log('Estado actual del formulario:', this.formData);
    console.log('Configuración avanzada actual:', this.formData.configuracion_avanzada);
    
    // Mostrar simulación antes de guardar
    this.mostrarSimulacionVenta();
  }

  /**
   * Cambiar estado activo/inactivo
   */
  toggleStatus(tipo: TipoClienteEmpresa): void {
    this.fidelizacionService.toggleStatus(tipo.id).subscribe({
      next: (response) => {
        if (response.success) {
          this.alertService.success('Exito','Estado actualizado exitosamente');
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
    if (confirm(`¿Está seguro de establecer "${tipo.nombre_efectivo}" como tipo por defecto? Los clientes sin tipo asignado usarán este.`)) {
      const updateData: UpdateTipoClienteRequest = {
        id_tipo_base: tipo.tipo_base?.id ?? (tipo as any).id_tipo_base ?? undefined,
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
            this.alertService.success('Exito','Tipo de cliente establecido como por defecto exitosamente');
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
      this.alertService.success('Exito', 'Descarga completada');
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

  /**
   * Formatear puntos por dólar para limitar a 2 decimales
   */
  formatPuntosPorDolar(event: any): void {
    const value = parseFloat(event.target.value);
    if (!isNaN(value)) {
      this.formData.puntos_por_dolar = Math.round(value * 100) / 100;
    }
  }

  /**
   * Formatear número a 2 decimales
   */
  formatDecimal(value: number): number {
    return Math.round(value * 100) / 100;
  }

  /**
   * Valor del punto en BD: decimal(8,4); no usar formatDecimal (2 cifras) para no perder precisión.
   */
  formatValorPuntoDecimal(value: number): number {
    if (isNaN(value)) {
      return 0;
    }
    return Math.round(value * 10000) / 10000;
  }

  /**
   * El API expone `valor_punto` en el tipo y a veces también en `configuracion_avanzada`.
   * Si solo se lee configuracion_avanzada y ahí no viene la clave, antes se caía a 0.
   */
  private resolveValorPuntoFromTipo(tipo: any): number {
    const tryParse = (raw: unknown): number | null => {
      if (raw === undefined || raw === null) return null;
      const s = String(raw).trim();
      if (s === '') return null;
      const n = parseFloat(s);
      return isNaN(n) ? null : n;
    };

    const fromRoot = tryParse(tipo?.valor_punto);
    if (fromRoot !== null) {
      return this.formatValorPuntoDecimal(fromRoot);
    }
    const fromAdv = tryParse(tipo?.configuracion_avanzada?.valor_punto);
    if (fromAdv !== null) {
      return this.formatValorPuntoDecimal(fromAdv);
    }
    return 0.01;
  }
}
