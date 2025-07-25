import { Component, OnInit, TemplateRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaConstants } from '../../../constants/planilla.constants';
import { ConfiguracionPlanillaService } from '@services/configuracion-planilla.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-planilla-detalle',
  templateUrl: './planilla-detalle.component.html',
})
export class PlanillaDetalleComponent implements OnInit {
  public planilla: any = {};
  public detalles: any[] = [];
  public loading: boolean = false;
  public procesando: boolean = false;
  public saving: boolean = false;
  public ESTADOS_PLANILLA = PlanillaConstants.ESTADOS_PLANILLA;
  public filtros: any = {
    buscador: '',
    estado: '',
    id_departamento: '',
    id_cargo: '',
    direccion: 'asc',
    orden: 'empleado.nombres',
    paginate: 10,
  };
  public notValue = false;

  public departamentos: any[] = [];
  public cargos: any[] = [];
  public cargosFiltrados: any[] = [];
  public vistaActual: string = 'empleados';
  public descuentosPatronales: any = null;
  public mostrarTodos: boolean = false;
  public downloading: boolean = false;
  public configPlanilla: any = null;
  public round: any = Math.round;
  public conceptosConfigurados: any = null;


  modalRef!: BsModalRef;
  detalleSeleccionado: any = null;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private configPlanillaService: ConfiguracionPlanillaService
  ) { }

  ngOnInit() {
    this.route.params.subscribe((params) => {
      if (params['id']) {
        this.loadPlanillas(params['id']);
      }
    });

    this.cargarCatalogos();
  }

  public cargarCatalogos() {
    this.apiService.getAll('departamentosPlanilla/list').subscribe({
      next: (departamentos) => {
        this.departamentos = departamentos;
      },
      error: (error) => this.alertService.error(error),
    });

    this.apiService.getAll('cargos/list').subscribe({
      next: (cargos) => {
        this.cargos = cargos;
      },
      error: (error) => this.alertService.error(error),
    });
  }

  public onDepartamentoChange() {
    if (this.filtros.id_departamento) {
      this.cargosFiltrados = this.cargos.filter(
        (cargo) => cargo.id_departamento == this.filtros.id_departamento
      );
    } else {
      this.cargosFiltrados = [];
      this.filtros.id_cargo = '';
    }
  }

  /*** Método para limpiar filtros y mostrar vista normal*/
  public limpiarFiltros() {
    this.vistaActual = 'empleados';
    this.mostrarTodos = false;
    this.filtros = {
      buscador: '',
      fecha: '',
      estado: '',
      id_departamento: '',
      id_cargo: '',
      paginate: 10,
    };
    this.cargosFiltrados = [];
    this.filtrarDetallePlanillas();
  }

  public loadPlanillas(id: number) {
    this.loading = true;
    const params = {
      ...this.filtros,
      id: id,
    };

    this.apiService.getAll('planillas/detalles', params).subscribe({
      next: (response) => {
        this.planilla = response;
        this.detalles = response.detalles.data;
        this.calcularTotalesPlanilla();
        this.loading = false;

        console.log('🧩 Detalles:', this.detalles);

        this.loadConceptosConfigurados();

        const pais = this.planilla?.empresa?.cod_pais;
        if (pais !== 'SV') {
          console.log('✅ Planilla de país diferente a El Salvador');
        } else {
          console.log('🇸🇻 Planilla salvadoreña (usa sistema legacy)');
        }


      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      },
    });
  }

  // calcularConcepto(detalle: any, concepto: any): number {
  //   const base = detalle[concepto.base_calculo]; // ej: detalle['salario_devengado']
  //   const tipo = concepto.tipo;
  //   const valor = concepto.valor;
  
  //   if (!base || !tipo) return 0;
  
  //   if (tipo === 'porcentaje') {
  //     return (base * valor) / 100;
  //   }

    
  
  //   // Otros tipos (como tabla) los veremos después
  //   return 0;
  // }

  calcularConcepto(detalle: any, concepto: any): number {
    const codigo = concepto.codigo;
    const codigoLower = codigo.toLowerCase();
  
    // 🎯 1. Si el campo existe en detalle (como "isss_empleado", "renta", etc.)
    if (detalle.hasOwnProperty(codigoLower)) {
      return Number(detalle[codigoLower]) || 0;
    }
  
    // 🎯 2. Si el tipo es porcentaje, aplicamos cálculo sobre base
    if (concepto.tipo === 'porcentaje') {
      const base = detalle[concepto.base_calculo];
      const tope = concepto.tope_maximo || null;
  
      let monto = Number(base) || 0;
  
      if (tope && monto > tope) {
        monto = tope;
      }
  
      return (monto * concepto.valor) / 100;
    }
  
    // 🎯 3. Si el tipo es fijo
    if (concepto.tipo === 'fijo') {
      return Number(concepto.valor) || 0;
    }
  
    // 🎯 4. Si es sistema existente pero el campo no está en detalle
    if (concepto.tipo === 'sistema_existente') {
      if (detalle.hasOwnProperty(codigoLower)) {
        return Number(detalle[codigoLower]) || 0;
      } else if (detalle.hasOwnProperty('renta') && codigo === 'RENTA') {
        return Number(detalle['renta']) || 0;
      }
    }
  
    // ❌ 5. Fallback
    return 0;
  }
  
  

  get esElSalvador(): boolean {
    return this.planilla?.empresa?.cod_pais === 'SV';
  }

  get conceptosEmpleado() {
    return Object.entries(this.conceptosConfigurados || {}).filter(
      ([, c]: any) => !c.es_patronal
    );
  }
  
  get conceptosPatronales() {
    return Object.entries(this.conceptosConfigurados || {}).filter(
      ([, c]: any) => c.es_patronal
    );
  }

  getTotalConcepto(codigo: string): number {
    let total = 0;
  
    // Validación defensiva
    if (!this.conceptosConfigurados || !this.detalles) return 0;
  
    const concepto = this.conceptosConfigurados[codigo];
    if (!concepto) return 0;
  
    for (const detalle of this.detalles) {
      const valor = this.calcularConcepto(detalle, concepto);
      total += Number(valor) || 0;
    }

    return total;
  }

  getTotalCampoReal(campo: string): number {
    let total = 0;
  
    for (const detalle of this.detalles) {
      if (detalle[campo] !== undefined && detalle[campo] !== null) {
        total += Number(detalle[campo]) || 0;
      }
    }
  
    return total;
  }
  
  
  
  
  getTotalCampo(campo: string): number {
    let total = 0;
  
    for (const detalle of this.detalles) {
      const valor = Number(detalle[campo]) || 0;
      total += valor;
    }
  
    return total;
  }


  getTotalAportesPatronales(detalle: any): number {
    return this.conceptosPatronales
      .map(([_, c]) => this.calcularConcepto(detalle, c))
      .reduce((a, b) => a + b, 0);
  }

  loadConceptosConfigurados() {
    this.configPlanillaService.obtenerConfiguracion().subscribe({
      next: (config) => {
        this.conceptosConfigurados = config?.configuracion?.conceptos || null;
        console.log('🧩 Conceptos personalizados cargados:', this.conceptosConfigurados);
      },
      error: () => {
        console.warn('⚠️ No hay configuración personalizada');
      }
    });
  }

  public filtrarPlanillas() {
    this.loadPlanillas(this.planilla.id);
    if (this.modalRef) {
      this.modalRef.hide();
    }
  }

  public editarDetalle(detalle: any) {
    if (this.planilla.estado !== 2) {
      this.alertService.warning(
        'Advertencia',
        'Solo se pueden editar planillas en estado borrador'
      );
      return;
    }
    this.detalleSeleccionado = { ...detalle };
    this.calcularTotales();

    setTimeout(() => {
      const element = document.querySelector('.card-header');
      element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
  }

  public cancelarEdicion() {
    this.detalleSeleccionado = null;
  }

  getEstadoDetalle(estado: number) {
    let estadoObject;
    switch (estado) {
      case this.ESTADOS_PLANILLA.INACTIVA:
        estadoObject = {
          nombre: 'Inactiva',
          className: 'bg-dark',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.ACTIVA:
        estadoObject = {
          nombre: 'Activa',
          className: 'bg-success',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.BORRADOR:
        estadoObject = {
          nombre: 'Borrador',
          className: 'bg-secondary',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.APROBADA:
        estadoObject = {
          nombre: 'Aprobada',
          className: 'bg-primary',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.PAGADA:
        estadoObject = {
          nombre: 'Pagada',
          className: 'bg-success',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.ANULADA:
        estadoObject = {
          nombre: 'Anulada',
          className: 'bg-danger',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.PENDIENTE:
        estadoObject = {
          nombre: 'Pendiente',
          className: 'bg-secondary',
        };
        return estadoObject;
      default:
        return {
          nombre: 'Desconocido',
          className: 'bg-secondary',
        };
    }
  }

  public guardarDetalle() {
    if (!this.detalleSeleccionado || !this.detalleSeleccionado.id) {
      this.alertService.error(
        'No se ha seleccionado ningún detalle para guardar'
      );
      return;
    }

    if (this.planilla.estado !== 2) {
      this.alertService.warning(
        'Advertencia',
        'Solo se pueden editar planillas en estado borrador'
      );
      return;
    }

    this.saving = true;

    const datosActualizados = {
      // Datos de entrada
      horas_extra: this.detalleSeleccionado.horas_extra || 0,
      monto_horas_extra: this.detalleSeleccionado.monto_horas_extra || 0,
      comisiones: this.detalleSeleccionado.comisiones || 0,
      bonificaciones: this.detalleSeleccionado.bonificaciones || 0,
      otros_ingresos: this.detalleSeleccionado.otros_ingresos || 0,
      dias_laborados: this.detalleSeleccionado.dias_laborados || 30,
      prestamos: this.detalleSeleccionado.prestamos || 0,
      anticipos: this.detalleSeleccionado.anticipos || 0,
      otros_descuentos: this.detalleSeleccionado.otros_descuentos || 0,
      descuentos_judiciales:
        this.detalleSeleccionado.descuentos_judiciales || 0,

      // Totales calculados
      salario_base: this.detalleSeleccionado.salario_base || 0,
      total_ingresos: this.detalleSeleccionado.total_ingresos || 0,

      // ISSS
      isss_empleado: this.detalleSeleccionado.isss_empleado || 0,
      isss_patronal: this.detalleSeleccionado.isss_patronal || 0,

      // AFP
      afp_empleado: this.detalleSeleccionado.afp_empleado || 0,
      afp_patronal: this.detalleSeleccionado.afp_patronal || 0,

      // Renta
      renta: this.detalleSeleccionado.renta || 0,

      // Totales finales
      total_descuentos: this.detalleSeleccionado.total_descuentos || 0,
      sueldo_neto: this.detalleSeleccionado.sueldo_neto || 0,

      // Comentarios o detalles adicionales
      detalle_otras_deducciones:
        this.detalleSeleccionado.detalle_otras_deducciones || '',
    };

    this.apiService
      .store(
        `planillas/detalles/editar/${this.detalleSeleccionado.id}`,
        datosActualizados
      )
      .subscribe({
        next: (response) => {
          this.alertService.success(
            'Éxito',
            'Detalle actualizado correctamente'
          );

          const index = this.detalles.findIndex(
            (d) => d.id === this.detalleSeleccionado.id
          );
          if (index !== -1) {
            this.detalles[index] = {
              ...response.detalle,
              empleado: response.empleado,
            };
          }

          if (response.planilla) {
            this.planilla = response.planilla;
          }

          this.calcularTotalesPlanilla();
          this.detalleSeleccionado = null;
          this.saving = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.saving = false;
        },
      });
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
  }

  public getEstadoPlanilla(estado: number): string {
    switch (estado) {
      case 0:
        return 'Inactiva';
      case 1:
        return 'Activa';
      case 2:
        return 'Borrador';
      case 3:
        return 'Pendiente';
      case 4:
        return 'Aprobada';
      case 5:
        return 'Pagada';
      case 6:
        return 'Anulada';
      default:
        return 'Desconocido';
    }
  }

  public getEstadoClass(estado: number): string {
    switch (estado) {
      case 0:
        return 'bg-dark';
      case 1:
        return 'bg-success';
      case 2:
        return 'bg-secondary';
      case 3:
        return 'bg-info';
      case 4:
        return 'bg-success';
      case 5:
        return 'bg-primary';
      case 6:
        return 'bg-danger';
      default:
        return 'bg-secondary';
    }
  }
  public getTextEstadoClass(estado: number): string {
    switch (estado) {
      case 0:
        return 'text-white';
      case 1:
        return 'text-dark';
      case 2:
        return 'text-white';
      case 3:
        return 'text-dark';
      case 4:
        return 'text-dark';
      case 5:
        return 'text-white';
      case 6:
        return 'text-dark';
      default:
        return 'text-white';
    }
  }

  public openFilter(template: TemplateRef<any>) {
    this.modalRef = this.modalService.show(template);
  }

  public filtrarDetallePlanillas() {
    this.loadPlanillas(this.planilla.id);
    this.modalRef?.hide();
  }

  openEditarDetalleModal(detalle: any, template: TemplateRef<any>) {
    this.detalleSeleccionado = { ...detalle };
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }



  private parseNumber(value: any): number {
    if (value === null || value === undefined || value === '') {
      return 0;
    }
    const parsed = parseFloat(value.toString().replace(/[^\d.-]/g, ''));
    return isNaN(parsed) ? 0 : parsed;
  }

  getTotalRegistros(): string {
    return `${this.planilla?.detalles?.total ?? 0} registros`;
  }

  withdrawPayroll(detalle: any) {
    if (this.planilla.estado !== 2) {
      this.alertService.warning(
        'Advertencia',
        'Solo se pueden modificar planillas en estado borrador'
      );
      return;
    }
    Swal.fire({
      title: '¿Está seguro?',
      text: '¿Está seguro de retirar este empleado de la planilla?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, retirar',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      } else {
        this.saving = true;
        this.apiService
          .store(`planillas/detalles/retirar/${detalle.id}`, {})
          .subscribe({
            next: (response) => {
              this.alertService.success(
                'Éxito',
                'Empleado retirado de la planilla exitosamente'
              );
              // Actualizar el estado del detalle localmente
              detalle.estado = 0;
              this.loadPlanillas(this.planilla.id);
              // this.calcularTotalesPlanilla();
              this.saving = false;
            },
            error: (error) => {
              this.alertService.error(
                'Error al retirar el empleado de la planilla'
              );
              this.saving = false;
            },
          });
      }
    });
  }

  includePayroll(detalle: any) {
    if (this.planilla.estado !== 2) {
      this.alertService.warning(
        'Advertencia',
        'Solo se pueden modificar planillas en estado borrador'
      );
      return;
    }

    Swal.fire({
      title: '¿Está seguro?',
      text: '¿Está seguro de incluir este empleado en la planilla?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, incluir',
    }).then((result: any) => {
      if (!result.isConfirmed) {
        return;
      } else {
        this.saving = true;
        this.apiService
          .store(`planillas/detalles/incluir/${detalle.id}`, {})
          .subscribe({
            next: (response) => {
              this.alertService.success(
                'Éxito',
                'Empleado incluido en la planilla exitosamente'
              );
              // Actualizar el estado del detalle localmente
              detalle.estado = 1;
              this.loadPlanillas(this.planilla.id);
              // this.calcularTotalesPlanilla();
              this.saving = false;
            },
            error: (error) => {
              this.alertService.error(
                'Error al incluir el empleado en la planilla'
              );
              this.saving = false;
            },
          });
      }
    });
  }

  public downloadInvoice(detalle: any) {
    Swal.fire({
      title: 'Generando boleta',
      text: 'Por favor espere...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    this.apiService
      .download(`planillas/detalles/${detalle.id}/boleta`)
      .subscribe({
        next: (response) => {
          // Crear blob y descargar
          const blob = new Blob([response], { type: 'application/pdf' });
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = `boleta_${this.planilla.codigo}_${detalle.empleado.codigo}.pdf`;
          link.click();
          window.URL.revokeObjectURL(url);

          Swal.fire({
            title: '¡Éxito!',
            text: 'Boleta generada correctamente',
            icon: 'success',
            timer: 1500,
          });
        },
        error: (error) => {
          Swal.fire({
            title: 'Error',
            text: 'Error al generar la boleta de pago',
            icon: 'error',
          });
          this.alertService.error(error);
        },
      });
  }

  /**
   * Método para mostrar todos los registros (sin paginación)
   */
  public mostrarTodosRegistros() {
    this.mostrarTodos = true;
    this.filtros.paginate = 1000; // Número alto para mostrar todos
    this.filtrarDetallePlanillas();
  }

  /**
   * Método para mostrar vista de empleados con paginación normal
   */
  public mostrarDetallesEmpleados() {
    this.vistaActual = 'empleados';
    this.mostrarTodos = false;
    this.filtros.paginate = 10; // Volver a paginación normal
    this.filtrarDetallePlanillas();
  }

  /**
   * Método para mostrar vista de descuentos patronales
   */
  public mostrarDescuentosPatronales() {
    this.vistaActual = 'descuentos_patronales';
    this.cargarDescuentosPatronales();
  }

  /**
   * Cargar datos de descuentos patronales
   */
  private cargarDescuentosPatronales() {
    this.loading = true;
    this.apiService.read('planillas/descuentos-patronales/', this.planilla.id).subscribe({
      next: (response) => {
        this.descuentosPatronales = response;
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error('Error al cargar los descuentos patronales');
        this.loading = false;
      }
    });
  }

  /**
   * Método para obtener el total de descuentos patronales
   */
  public getTotalDescuentosPatronales(): number {
    if (!this.descuentosPatronales) return 0;
    return this.descuentosPatronales.detalles?.reduce((total: number, detalle: any) => {
      return total + (detalle.isss_patronal || 0) + (detalle.afp_patronal || 0);
    }, 0) || 0;
  }

  private readonly RENTA_2025 = {
    MENSUAL: {
      TRAMOS: [
        { desde: 0.01, hasta: 550.00, porcentaje: 0.00, sobreExceso: 0.00, cuotaFija: 0.00 },
        { desde: 550.01, hasta: 895.24, porcentaje: 0.10, sobreExceso: 550.00, cuotaFija: 17.67 },
        { desde: 895.25, hasta: 2038.10, porcentaje: 0.20, sobreExceso: 895.24, cuotaFija: 60.00 },
        { desde: 2038.11, hasta: 999999.99, porcentaje: 0.30, sobreExceso: 2038.10, cuotaFija: 288.57 }
      ]
    },
    QUINCENAL: {
      TRAMOS: [
        { desde: 0.01, hasta: 275.00, porcentaje: 0.00, sobreExceso: 0.00, cuotaFija: 0.00 },
        { desde: 275.01, hasta: 447.62, porcentaje: 0.10, sobreExceso: 275.00, cuotaFija: 8.83 },
        { desde: 447.63, hasta: 1019.05, porcentaje: 0.20, sobreExceso: 447.62, cuotaFija: 30.00 },
        { desde: 1019.06, hasta: 999999.99, porcentaje: 0.30, sobreExceso: 1019.05, cuotaFija: 144.28 }
      ]
    },
    SEMANAL: {
      TRAMOS: [
        { desde: 0.01, hasta: 137.50, porcentaje: 0.00, sobreExceso: 0.00, cuotaFija: 0.00 },
        { desde: 137.51, hasta: 223.81, porcentaje: 0.10, sobreExceso: 137.50, cuotaFija: 4.42 },
        { desde: 223.82, hasta: 509.52, porcentaje: 0.20, sobreExceso: 223.81, cuotaFija: 15.00 },
        { desde: 509.53, hasta: 999999.99, porcentaje: 0.30, sobreExceso: 509.52, cuotaFija: 72.14 }
      ]
    },
    DEDUCCION_EMPLEADOS_ASALARIADOS: 1600.00
  };

  /**
   * Calcular renta según las nuevas tablas 2025
   */
  private calcularRenta2025(salarioDevengado: number, isssEmpleado: number, afpEmpleado: number, tipoPlanilla: string = 'mensual'): number {
    try {
      // Calcular salario gravado
      const salarioGravado = this.calcularSalarioGravado2025(salarioDevengado, isssEmpleado, afpEmpleado, tipoPlanilla);

      // Obtener tramos según tipo de planilla
      const tramos = this.obtenerTramos2025(tipoPlanilla);

      // Buscar tramo correspondiente
      for (const tramo of tramos) {
        if (salarioGravado >= tramo.desde && salarioGravado <= tramo.hasta) {
          const exceso = salarioGravado - tramo.sobreExceso;
          const retencion = tramo.cuotaFija + (exceso * tramo.porcentaje);
          return Math.round(retencion * 100) / 100;
        }
      }

      // Si no se encuentra tramo, usar el último
      const ultimoTramo = tramos[tramos.length - 1];
      const exceso = salarioGravado - ultimoTramo.sobreExceso;
      const retencion = ultimoTramo.cuotaFija + (exceso * ultimoTramo.porcentaje);
      return Math.round(retencion * 100) / 100;

    } catch (error) {
      console.error('Error calculando renta 2025:', error);
      return 0;
    }
  }

  /**
   * Calcular salario gravado para efectos de renta
   */
  private calcularSalarioGravado2025(salarioDevengado: number, isssEmpleado: number, afpEmpleado: number, tipoPlanilla: string): number {
    // Salario gravado = salario devengado - deducciones de seguridad social
    let salarioGravado = salarioDevengado - isssEmpleado - afpEmpleado;

    // Aplicar deducción de empleados asalariados si corresponde
    const salarioAnualEstimado = this.extrapolarSalarioAnual(salarioGravado, tipoPlanilla);

    if (salarioAnualEstimado <= 9100.00) {
      const deduccionProporcional = this.calcularDeduccionProporcional(tipoPlanilla);
      salarioGravado = Math.max(0, salarioGravado - deduccionProporcional);
    }

    return Math.round(salarioGravado * 100) / 100;
  }

  /**
   * Extrapolar salario a anual según tipo de planilla
   */
  private extrapolarSalarioAnual(salario: number, tipoPlanilla: string): number {
    switch (tipoPlanilla?.toLowerCase()) {
      case 'quincenal':
        return salario * 24; // 24 quincenas al año
      case 'semanal':
        return salario * 52; // 52 semanas al año
      default: // mensual
        return salario * 12; // 12 meses al año
    }
  }

  /**
   * Calcular deducción proporcional según tipo de planilla
   */
  private calcularDeduccionProporcional(tipoPlanilla: string): number {
    const deduccionAnual = 1600.00; // Usar constante del backend si está disponible
  
    switch (tipoPlanilla?.toLowerCase()) {
      case 'quincenal':
        return Math.round((deduccionAnual / 24) * 100) / 100;
      case 'semanal':
        return Math.round((deduccionAnual / 52) * 100) / 100;
      default: // mensual
        return Math.round((deduccionAnual / 12) * 100) / 100;
    }
  }

  /**
   * Obtener tramos según tipo de planilla
   */
  private obtenerTramos2025(tipoPlanilla: string): any[] {
    switch (tipoPlanilla) {
      case 'quincenal':
        return this.RENTA_2025.QUINCENAL.TRAMOS;
      case 'semanal':
        return this.RENTA_2025.SEMANAL.TRAMOS;
      default: // mensual
        return this.RENTA_2025.MENSUAL.TRAMOS;
    }
  }

  /**
   * Obtener información detallada del tramo aplicado
   */
  private obtenerInformacionTramo2025(salarioGravado: number, tipoPlanilla: string): any {
    const tramos = this.obtenerTramos2025(tipoPlanilla);

    for (let i = 0; i < tramos.length; i++) {
      const tramo = tramos[i];
      if (salarioGravado >= tramo.desde && salarioGravado <= tramo.hasta) {
        return {
          tramoNumero: i + 1,
          desde: tramo.desde,
          hasta: tramo.hasta,
          porcentaje: tramo.porcentaje * 100, // Convertir a porcentaje
          sobreExceso: tramo.sobreExceso,
          cuotaFija: tramo.cuotaFija,
          exceso: salarioGravado - tramo.sobreExceso,
          retencionCalculada: this.calcularRenta2025(salarioGravado + (salarioGravado * 0.1025), salarioGravado * 0.03, salarioGravado * 0.0725, tipoPlanilla)
        };
      }
    }

    return null;
  }

  public calcularTotales() {
    if (!this.detalleSeleccionado) {
      return;
    }
  
    // Obtener valores base
    const salarioBase = Number(this.detalleSeleccionado.salario_base) || 0;
    const diasLaborados = Number(this.detalleSeleccionado.dias_laborados) || 30;
    const horasExtra = Number(this.detalleSeleccionado.horas_extra) || 0;
    const comisiones = Number(this.detalleSeleccionado.comisiones) || 0;
    const bonificaciones = Number(this.detalleSeleccionado.bonificaciones) || 0;
    const otrosIngresos = Number(this.detalleSeleccionado.otros_ingresos) || 0;
  
    // Calcular salario devengado
    const salarioDevengado = (salarioBase / 30) * diasLaborados;
    this.detalleSeleccionado.salario_devengado = Number(salarioDevengado.toFixed(2));
  
    let montoHorasExtra = 0;
    if (horasExtra > 0) {
      const valorHoraNormal = salarioBase / 30 / 8; // Valor hora normal
      montoHorasExtra = horasExtra * (valorHoraNormal * 1.25); // 25% de recargo
    }
    this.detalleSeleccionado.monto_horas_extra = Number(montoHorasExtra.toFixed(2));
  
    // Calcular total de ingresos (AHORA INCLUYE LAS HORAS EXTRA CALCULADAS)
    const totalIngresos = salarioDevengado + montoHorasExtra + comisiones + bonificaciones + otrosIngresos;
    this.detalleSeleccionado.total_ingresos = Number(totalIngresos.toFixed(2));
  
    // ✅ NUEVO: VERIFICAR TIPO DE CONTRATO
    const tipoContrato = this.detalleSeleccionado.empleado?.tipo_contrato || 1; // Default: Permanente
    const esServiciosProfesionales = tipoContrato === 4; // TIPO_CONTRATO_SERVICIOS_PROFESIONALES
  
    // ✅ NUEVO: CALCULAR DEDUCCIONES SEGÚN TIPO DE CONTRATO
    let isssEmpleado = 0;
    let afpEmpleado = 0;
    let isssPatronal = 0;
    let afpPatronal = 0;
  
    if (esServiciosProfesionales) {
      // SERVICIOS PROFESIONALES: Sin ISSS ni AFP
      isssEmpleado = 0;
      afpEmpleado = 0;
      isssPatronal = 0;
      afpPatronal = 0;
    } else {
      // EMPLEADOS ASALARIADOS: Con ISSS y AFP normales
      const baseISSSEmpleado = Math.min(totalIngresos, 1000.00); // Tope de $1,000
      isssEmpleado = baseISSSEmpleado * 0.03; // 3%
      afpEmpleado = totalIngresos * 0.0725; // 7.25%
      isssPatronal = baseISSSEmpleado * 0.075; // 7.5% sobre base con tope
      afpPatronal = totalIngresos * 0.0875; // ✅ CORREGIDO: 8.75% (antes era 0.0773)
    }
  
    this.detalleSeleccionado.isss_empleado = Number(isssEmpleado.toFixed(2));
    this.detalleSeleccionado.afp_empleado = Number(afpEmpleado.toFixed(2));
    this.detalleSeleccionado.isss_patronal = Number(isssPatronal.toFixed(2));
    this.detalleSeleccionado.afp_patronal = Number(afpPatronal.toFixed(2));
  
    // ✅ NUEVO: CALCULAR RENTA SEGÚN TIPO DE CONTRATO
    let renta = 0;
    if (esServiciosProfesionales) {
      // SERVICIOS PROFESIONALES: 10% fijo sobre total de ingresos
      renta = totalIngresos * 0.10;
    } else {
      // EMPLEADOS ASALARIADOS: Usar tablas de renta normales
      renta = this.calcularRentaConConstantesBackend(totalIngresos, isssEmpleado, afpEmpleado, this.planilla.tipo_planilla);
    }
  
    this.detalleSeleccionado.renta = Number(renta.toFixed(2));
  
    // Calcular otros descuentos
    const prestamos = Number(this.detalleSeleccionado.prestamos) || 0;
    const anticipos = Number(this.detalleSeleccionado.anticipos) || 0;
    const otrosDescuentos = Number(this.detalleSeleccionado.otros_descuentos) || 0;
    const descuentosJudiciales = Number(this.detalleSeleccionado.descuentos_judiciales) || 0;
  
    // Calcular total de descuentos
    const totalDescuentos = isssEmpleado + afpEmpleado + renta + prestamos + anticipos + otrosDescuentos + descuentosJudiciales;
    this.detalleSeleccionado.total_descuentos = Number(totalDescuentos.toFixed(2));
  
    // Calcular sueldo neto
    const sueldoNeto = totalIngresos - totalDescuentos;
    this.detalleSeleccionado.sueldo_neto = Number(sueldoNeto.toFixed(2));
  
    // ✅ CONDICIONAL: Solo actualizar renta si es empleado asalariado
    if (!esServiciosProfesionales) {
      this.actualizarRenta();
    }
  }

  public getTipoContratoNombre(tipoContrato: number): string {
    switch (tipoContrato) {
      case 1: return 'Permanente';
      case 2: return 'Temporal';
      case 3: return 'Por obra';
      case 4: return 'Servicios Profesionales';
      default: return 'Desconocido';
    }
  }

  public esServiciosProfesionales(): boolean {
    return this.detalleSeleccionado?.empleado?.tipo_contrato === 4;
  }
  
  // Agregar después de calcularTotales()
  private actualizarRenta() {
    if (!this.detalleSeleccionado) return;
    
    const totalIngresos = Number(this.detalleSeleccionado.total_ingresos) || 0;
    const isssEmpleado = Number(this.detalleSeleccionado.isss_empleado) || 0;
    const afpEmpleado = Number(this.detalleSeleccionado.afp_empleado) || 0;
    
    const renta = this.calcularRenta2025(totalIngresos, isssEmpleado, afpEmpleado, this.planilla.tipo_planilla);
    this.detalleSeleccionado.renta = renta;
  }

  private calcularRentaConConstantesBackend(totalIngresos: number, isssEmpleado: number, afpEmpleado: number, tipoPlanilla: string): number {
    try {
      // Calcular salario gravado
      const salarioGravado = this.calcularSalarioGravadoCorregido(totalIngresos, isssEmpleado, afpEmpleado, tipoPlanilla);
      
      // Usar las constantes que ya tienes del backend
      const constants = PlanillaConstants.constants;
      if (!constants) {
        console.error('No se encontraron constantes del backend');
        return 0;
      }
      
      // Obtener tramos según tipo de planilla desde las constantes del backend
      let tramos = [];
      switch (tipoPlanilla?.toLowerCase()) {
        case 'quincenal':
          tramos = [
            {
              desde: constants.RENTA_QUINCENAL_TRAMO_1_DESDE,
              hasta: constants.RENTA_QUINCENAL_TRAMO_1_HASTA,
              porcentaje: constants.RENTA_QUINCENAL_TRAMO_1_PORCENTAJE,
              sobreExceso: constants.RENTA_QUINCENAL_TRAMO_1_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_QUINCENAL_TRAMO_1_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_QUINCENAL_TRAMO_2_DESDE,
              hasta: constants.RENTA_QUINCENAL_TRAMO_2_HASTA,
              porcentaje: constants.RENTA_QUINCENAL_TRAMO_2_PORCENTAJE,
              sobreExceso: constants.RENTA_QUINCENAL_TRAMO_2_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_QUINCENAL_TRAMO_2_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_QUINCENAL_TRAMO_3_DESDE,
              hasta: constants.RENTA_QUINCENAL_TRAMO_3_HASTA,
              porcentaje: constants.RENTA_QUINCENAL_TRAMO_3_PORCENTAJE,
              sobreExceso: constants.RENTA_QUINCENAL_TRAMO_3_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_QUINCENAL_TRAMO_3_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_QUINCENAL_TRAMO_4_DESDE,
              hasta: constants.RENTA_QUINCENAL_TRAMO_4_HASTA,
              porcentaje: constants.RENTA_QUINCENAL_TRAMO_4_PORCENTAJE,
              sobreExceso: constants.RENTA_QUINCENAL_TRAMO_4_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_QUINCENAL_TRAMO_4_CUOTA_FIJA
            }
          ];
          break;
        case 'semanal':
          tramos = [
            {
              desde: constants.RENTA_SEMANAL_TRAMO_1_DESDE,
              hasta: constants.RENTA_SEMANAL_TRAMO_1_HASTA,
              porcentaje: constants.RENTA_SEMANAL_TRAMO_1_PORCENTAJE,
              sobreExceso: constants.RENTA_SEMANAL_TRAMO_1_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_SEMANAL_TRAMO_1_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_SEMANAL_TRAMO_2_DESDE,
              hasta: constants.RENTA_SEMANAL_TRAMO_2_HASTA,
              porcentaje: constants.RENTA_SEMANAL_TRAMO_2_PORCENTAJE,
              sobreExceso: constants.RENTA_SEMANAL_TRAMO_2_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_SEMANAL_TRAMO_2_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_SEMANAL_TRAMO_3_DESDE,
              hasta: constants.RENTA_SEMANAL_TRAMO_3_HASTA,
              porcentaje: constants.RENTA_SEMANAL_TRAMO_3_PORCENTAJE,
              sobreExceso: constants.RENTA_SEMANAL_TRAMO_3_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_SEMANAL_TRAMO_3_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_SEMANAL_TRAMO_4_DESDE,
              hasta: constants.RENTA_SEMANAL_TRAMO_4_HASTA,
              porcentaje: constants.RENTA_SEMANAL_TRAMO_4_PORCENTAJE,
              sobreExceso: constants.RENTA_SEMANAL_TRAMO_4_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_SEMANAL_TRAMO_4_CUOTA_FIJA
            }
          ];
          break;
        default: // mensual
          tramos = [
            {
              desde: constants.RENTA_MENSUAL_TRAMO_1_DESDE,
              hasta: constants.RENTA_MENSUAL_TRAMO_1_HASTA,
              porcentaje: constants.RENTA_MENSUAL_TRAMO_1_PORCENTAJE,
              sobreExceso: constants.RENTA_MENSUAL_TRAMO_1_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_MENSUAL_TRAMO_1_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_MENSUAL_TRAMO_2_DESDE,
              hasta: constants.RENTA_MENSUAL_TRAMO_2_HASTA,
              porcentaje: constants.RENTA_MENSUAL_TRAMO_2_PORCENTAJE,
              sobreExceso: constants.RENTA_MENSUAL_TRAMO_2_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_MENSUAL_TRAMO_2_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_MENSUAL_TRAMO_3_DESDE,
              hasta: constants.RENTA_MENSUAL_TRAMO_3_HASTA,
              porcentaje: constants.RENTA_MENSUAL_TRAMO_3_PORCENTAJE,
              sobreExceso: constants.RENTA_MENSUAL_TRAMO_3_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_MENSUAL_TRAMO_3_CUOTA_FIJA
            },
            {
              desde: constants.RENTA_MENSUAL_TRAMO_4_DESDE,
              hasta: constants.RENTA_MENSUAL_TRAMO_4_HASTA,
              porcentaje: constants.RENTA_MENSUAL_TRAMO_4_PORCENTAJE,
              sobreExceso: constants.RENTA_MENSUAL_TRAMO_4_SOBRE_EXCESO,
              cuotaFija: constants.RENTA_MENSUAL_TRAMO_4_CUOTA_FIJA
            }
          ];
          break;
      }
      
      // Buscar tramo correspondiente y calcular retención
      for (const tramo of tramos) {
        if (salarioGravado >= tramo.desde && salarioGravado <= tramo.hasta) {
          const exceso = Math.max(0, salarioGravado - tramo.sobreExceso);
          const retencion = tramo.cuotaFija + (exceso * tramo.porcentaje);
          return Math.round(retencion * 100) / 100;
        }
      }
      
      return 0;
      
    } catch (error) {
      console.error('Error calculando renta con constantes del backend:', error);
      return 0;
    }
  }

  private calcularSalarioGravadoCorregido(totalIngresos: number, isssEmpleado: number, afpEmpleado: number, tipoPlanilla: string): number {
    // Salario gravado = total ingresos - deducciones de seguridad social
    let salarioGravado = totalIngresos - isssEmpleado - afpEmpleado;
    
    // Aplicar deducción de empleados asalariados si corresponde
    const salarioAnualEstimado = this.extrapolarSalarioAnual(salarioGravado, tipoPlanilla);
    
    if (salarioAnualEstimado <= 9100.00) {
      const deduccionProporcional = this.calcularDeduccionProporcional(tipoPlanilla);
      salarioGravado = Math.max(0, salarioGravado - deduccionProporcional);
    }
    
    return Math.round(salarioGravado * 100) / 100;
  }
  

  /**
   * Mostrar información detallada del cálculo de renta
   */
  public mostrarDetalleCalculoRenta(detalle: any) {
    if (!detalle) return;

    const salarioDevengado = Number(detalle.salario_devengado) || 0;
    const montoHorasExtra = Number(detalle.monto_horas_extra) || 0;
    const comisiones = Number(detalle.comisiones) || 0;
    const bonificaciones = Number(detalle.bonificaciones) || 0;
    const otrosIngresos = Number(detalle.otros_ingresos) || 0;

    const totalIngresos = salarioDevengado + montoHorasExtra + comisiones + bonificaciones + otrosIngresos;
    const isssEmpleado = PlanillaConstants.calcularDescuentoISSSEmpleado(totalIngresos);
    const afpEmpleado = PlanillaConstants.calcularDescuentoAFPEmpleado(totalIngresos);
    const salarioGravado = PlanillaConstants.calcularSalarioGravado(
      totalIngresos,
      isssEmpleado,
      afpEmpleado,
      this.planilla.tipo_planilla
    );

    const infoTramo = PlanillaConstants.obtenerInformacionTramo(salarioGravado, this.planilla.tipo_planilla);

    if (!infoTramo) {
      this.alertService.warning('Advertencia', 'No se pudo determinar el tramo de renta aplicable');
      return;
    }

    // Crear mensaje detallado
    const mensaje = `
      <div style="text-align: left;">
        <h4>📊 Detalle de Cálculo de Renta - ${detalle.empleado?.nombres} ${detalle.empleado?.apellidos}</h4>
        <hr>
        
        <h5>💰 Ingresos:</h5>
        <p>• Total Ingresos: <strong>$${totalIngresos.toFixed(2)}</strong></p>
        
        <h5>📉 Descuentos de Seguridad Social:</h5>
        <p>• ISSS Empleado (3%): <strong>$${isssEmpleado.toFixed(2)}</strong></p>
        <p>• AFP Empleado (7.25%): <strong>$${afpEmpleado.toFixed(2)}</strong></p>
        
        <h5>💵 Base Gravable:</h5>
        <p>• Salario Gravado: <strong>$${salarioGravado.toFixed(2)}</strong></p>
        
        <h5>📋 Tramo Aplicado (${this.planilla.tipo_planilla}):</h5>
        <p>• Tramo: <strong>${infoTramo.tramo_numero}</strong></p>
        <p>• Rango: <strong>$${infoTramo.desde} - $${infoTramo.hasta}</strong></p>
        <p>• Porcentaje: <strong>${infoTramo.porcentaje}%</strong></p>
        <p>• Sobre exceso de: <strong>$${infoTramo.sobre_exceso}</strong></p>
        <p>• Cuota fija: <strong>$${infoTramo.cuota_fija}</strong></p>
        <p>• Exceso: <strong>$${infoTramo.exceso.toFixed(2)}</strong></p>
        
        <h5>🎯 Resultado:</h5>
        <p>• <strong>Retención calculada: $${infoTramo.retencion_calculada.toFixed(2)}</strong></p>
        
        <hr>
        <small><em>Decreto No. 10 - Abril 2025</em></small>
      </div>
    `;

    // Usar SweetAlert2 para mejor presentación
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: '📊 Detalle de Cálculo de Renta',
        html: mensaje,
        icon: 'info',
        width: '600px',
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#3085d6'
      });
    } else {
      // Fallback si no hay SweetAlert2
      this.alertService.info('Detalle de Cálculo de Renta', mensaje);
    }
  }

  /**
   * Validar que los cálculos estén correctos
   */
  public validarCalculos(): boolean {
    if (!this.detalleSeleccionado) return false;

    const salarioDevengado = Number(this.detalleSeleccionado.salario_devengado) || 0;
    if (salarioDevengado <= 0) {
      this.alertService.warning('Advertencia', 'El salario devengado debe ser mayor a cero');
      return false;
    }

    const diasLaborados = Number(this.detalleSeleccionado.dias_laborados) || 0;
    const diasMaximos = this.planilla.tipo_planilla === 'quincenal' ? 15 :
      this.planilla.tipo_planilla === 'semanal' ? 7 : 30;

    if (diasLaborados > diasMaximos) {
      this.alertService.warning('Advertencia',
        `Los días laborados no pueden exceder ${diasMaximos} para planilla ${this.planilla.tipo_planilla}`);
      return false;
    }

    // Validar que las constantes estén cargadas
    if (!PlanillaConstants.isLoaded()) {
      this.alertService.error('Las constantes de planilla no están cargadas. Recargue la página.');
      return false;
    }

    return true;
  }

  /**
   * Método para aplicar recálculo de renta (junio/diciembre)
   */
  public aplicarRecalculoRenta() {
    const mesActual = new Date().getMonth() + 1;

    if (mesActual !== 6 && mesActual !== 12) {
      this.alertService.error('El recálculo de renta solo se aplica en junio y diciembre');
      return;
    }

    const tipoRecalculo = mesActual === 6 ? 'junio' : 'diciembre';

    Swal.fire({
      title: '¿Está seguro?',
      text: `¿Está seguro de aplicar el recálculo de renta para ${tipoRecalculo}? Esta acción modificará las retenciones de todos los empleados según las tablas de recálculo.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, aplicar recálculo',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.store(`planillas/recalculo-renta/${this.planilla.id}`, {
          tipo_recalculo: tipoRecalculo
        }).subscribe({
          next: (response) => {
            Swal.fire({
              title: 'Éxito',
              text: 'Recálculo de renta aplicado correctamente',
              icon: 'success'
            });
            this.loadPlanillas(this.planilla.id); // Recargar datos
          },
          error: (error) => {
            Swal.fire({
              title: 'Error',
              text: 'Error al aplicar recálculo de renta',
              icon: 'error'
            });
          }
        });
      }
    });
  }

  getIngresosAdicionales(detalle: any): number {
    // console.log('Detalle valores:', {
    //   bonificaciones: detalle.bonificaciones,
    //   comisiones: detalle.comisiones,
    //   monto_horas_extra: detalle.monto_horas_extra,
    //   otros_ingresos: detalle.otros_ingresos
    // });
    
    const bon = parseFloat(detalle.bonificaciones) || 0;
    const com = parseFloat(detalle.comisiones) || 0;
    const horas = parseFloat(detalle.monto_horas_extra) || 0;
    const otros = parseFloat(detalle.otros_ingresos) || 0;
    
    return bon + com + horas + otros;
  }

  calcularValoresDetalle() {
    if (!this.detalleSeleccionado) return;

    // Obtener valores de entrada
    const salarioDevengado = Number(this.detalleSeleccionado.salario_devengado) || 0;
    const montoHorasExtra = Number(this.detalleSeleccionado.monto_horas_extra) || 0;
    const comisiones = Number(this.detalleSeleccionado.comisiones) || 0;
    const bonificaciones = Number(this.detalleSeleccionado.bonificaciones) || 0;
    const otrosIngresos = Number(this.detalleSeleccionado.otros_ingresos) || 0;
    const prestamos = Number(this.detalleSeleccionado.prestamos) || 0;
    const anticipos = Number(this.detalleSeleccionado.anticipos) || 0;
    const otrosDescuentos = Number(this.detalleSeleccionado.otros_descuentos) || 0;
    const descuentosJudiciales = Number(this.detalleSeleccionado.descuentos_judiciales) || 0;

    // Calcular usando las nuevas constantes 2025
    const calculos = PlanillaConstants.calcularDescuentosEmpleado(
      salarioDevengado,
      montoHorasExtra,
      comisiones,
      bonificaciones,
      otrosIngresos,
      this.planilla.tipo_planilla
    );

    // Asignar valores calculados
    this.detalleSeleccionado.total_ingresos = calculos.totalIngresos;
    this.detalleSeleccionado.isss_empleado = calculos.isssEmpleado;
    this.detalleSeleccionado.isss_patronal = calculos.isssPatronal;
    this.detalleSeleccionado.afp_empleado = calculos.afpEmpleado;
    this.detalleSeleccionado.afp_patronal = calculos.afpPatronal;
    this.detalleSeleccionado.renta = calculos.renta;

    // Calcular total de descuentos incluyendo otros descuentos
    const totalDescuentos = calculos.isssEmpleado + calculos.afpEmpleado + calculos.renta +
      prestamos + anticipos + otrosDescuentos + descuentosJudiciales;

    this.detalleSeleccionado.total_descuentos = Math.round(totalDescuentos * 100) / 100;

    // Calcular sueldo neto
    this.detalleSeleccionado.sueldo_neto = Math.round((calculos.totalIngresos - totalDescuentos) * 100) / 100;
  }

  compararCalculos(detalle: any) {
    if (!detalle) return;

    const salarioDevengado = Number(detalle.salario_devengado) || 0;
    const montoHorasExtra = Number(detalle.monto_horas_extra) || 0;
    const comisiones = Number(detalle.comisiones) || 0;
    const bonificaciones = Number(detalle.bonificaciones) || 0;
    const otrosIngresos = Number(detalle.otros_ingresos) || 0;

    const totalIngresos = salarioDevengado + montoHorasExtra + comisiones + bonificaciones + otrosIngresos;
    const isssEmpleado = PlanillaConstants.calcularDescuentoISSSEmpleado(totalIngresos);
    const afpEmpleado = PlanillaConstants.calcularDescuentoAFPEmpleado(totalIngresos);

    // Cálculo con método legacy
    const rentaLegacy = PlanillaConstants.calcularRenta(totalIngresos - isssEmpleado - afpEmpleado);

    // Cálculo con nuevas tablas
    const salarioGravado = PlanillaConstants.calcularSalarioGravado(
      totalIngresos, isssEmpleado, afpEmpleado, this.planilla.tipo_planilla
    );
    const rentaNueva = PlanillaConstants.calcularRetencionRenta(salarioGravado, this.planilla.tipo_planilla);

    const diferencia = rentaNueva - rentaLegacy;

    console.log('🔍 Comparación de cálculos:', {
      empleado: `${detalle.empleado?.nombres} ${detalle.empleado?.apellidos}`,
      totalIngresos,
      salarioGravado,
      rentaLegacy: rentaLegacy.toFixed(2),
      rentaNueva: rentaNueva.toFixed(2),
      diferencia: diferencia.toFixed(2),
      tipoPlanilla: this.planilla.tipo_planilla
    });

    return {
      rentaLegacy,
      rentaNueva,
      diferencia,
      salarioGravado
    };
  }

  public calcularValorHora(): number {
    if (!this.detalleSeleccionado) return 0;
  
    const salarioBase = Number(this.detalleSeleccionado.salario_base) || 0;
    
    let salarioBaseAjustado = salarioBase;
    let diasReferencia = 30;
  
    if (this.planilla.tipo_planilla === 'quincenal') {
      salarioBaseAjustado = salarioBase / 2;
      diasReferencia = 15;
    } else if (this.planilla.tipo_planilla === 'semanal') {
      salarioBaseAjustado = salarioBase / 4.33;
      diasReferencia = 7;
    }
  
    const valorHoraNormal = salarioBaseAjustado / diasReferencia / 8;
    const valorHoraExtra = valorHoraNormal * 1.25; // 25% de recargo
    
    return Number(valorHoraExtra.toFixed(2));
  }

  
  public exportarVistaActual() {
    this.downloading = true;
    
    const filtrosExport = {
        id_planilla: this.planilla.id,
        vista: this.vistaActual, // 'empleados' o 'descuentos_patronales'
        ...this.filtros // Incluir todos los filtros actuales
    };

    this.apiService.export('planillas/detalles/exportar', filtrosExport).subscribe(
        (data: Blob) => {
            const blob = new Blob([data], { 
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            
            // Generar nombre del archivo basado en la vista actual
            const tipoExport = this.vistaActual === 'descuentos_patronales' ? 'patronales' : 'empleados';
            a.download = `planilla_${this.planilla.codigo}_${tipoExport}.xlsx`;
            
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
        },
        (error) => {
            this.alertService.error(error);
            this.downloading = false;
        }
    );
}

public calcularDescuentos(): void {
  if (!this.detalleSeleccionado || !this.planilla) {
    return;
  }

  // Preparar datos del empleado
  const datosEmpleado = {
    salario_base: Number(this.detalleSeleccionado.salario_base) || 0,
    salario_devengado: Number(this.detalleSeleccionado.salario_devengado) || 0,
    dias_laborados: Number(this.detalleSeleccionado.dias_laborados) || 30,
    horas_extra: Number(this.detalleSeleccionado.horas_extra) || 0,
    monto_horas_extra: Number(this.detalleSeleccionado.monto_horas_extra) || 0,
    comisiones: Number(this.detalleSeleccionado.comisiones) || 0,
    bonificaciones: Number(this.detalleSeleccionado.bonificaciones) || 0,
    otros_ingresos: Number(this.detalleSeleccionado.otros_ingresos) || 0,
    prestamos: Number(this.detalleSeleccionado.prestamos) || 0,
    anticipos: Number(this.detalleSeleccionado.anticipos) || 0,
    otros_descuentos: Number(this.detalleSeleccionado.otros_descuentos) || 0,
    descuentos_judiciales: Number(this.detalleSeleccionado.descuentos_judiciales) || 0,
    tipo_contrato: this.detalleSeleccionado.empleado?.tipo_contrato
  };

  // Intentar usar sistema configurable
  this.configPlanillaService.probarCalculo(datosEmpleado).subscribe({
    next: (resultado) => {
      this.aplicarResultadosConfigurables(resultado);

      
    },
    error: (error) => {
      console.warn('Fallback al sistema legacy:', error);
      this.calcularDescuentosLegacy();
    }
  });
}

private aplicarResultadosConfigurables(resultado: any): void {
  const resultados = resultado.resultados;
  
  // Aplicar valores calculados
  this.detalleSeleccionado.isss_empleado = this.round(resultados.isss_empleado || 0);
  this.detalleSeleccionado.isss_patronal = this.round(resultados.isss_patronal || 0);
  this.detalleSeleccionado.afp_empleado = this.round(resultados.afp_empleado || 0);
  this.detalleSeleccionado.afp_patronal = this.round(resultados.afp_patronal || 0);
  this.detalleSeleccionado.renta = this.round(resultados.renta || 0);
  
  // Aplicar totales
  if (resultados.totales) {
    this.detalleSeleccionado.total_ingresos = this.round(resultados.totales.total_ingresos || 0);
    this.detalleSeleccionado.total_descuentos = this.round(resultados.totales.total_deducciones || 0);
    this.detalleSeleccionado.sueldo_neto = this.round(resultados.totales.sueldo_neto || 0);
  }

  // Recalcular totales de planilla
  this.calcularTotalesPlanilla();
  
  console.log('✅ Usando sistema configurable');
}

private calcularDescuentosLegacy(): void {
  const salarioDevengado = Number(this.detalleSeleccionado.salario_devengado) || 0;
  const montoHorasExtra = Number(this.detalleSeleccionado.monto_horas_extra) || 0;
  const comisiones = Number(this.detalleSeleccionado.comisiones) || 0;
  const bonificaciones = Number(this.detalleSeleccionado.bonificaciones) || 0;
  const otrosIngresos = Number(this.detalleSeleccionado.otros_ingresos) || 0;
  const prestamos = Number(this.detalleSeleccionado.prestamos) || 0;
  const anticipos = Number(this.detalleSeleccionado.anticipos) || 0;
  const otrosDescuentos = Number(this.detalleSeleccionado.otros_descuentos) || 0;
  const descuentosJudiciales = Number(this.detalleSeleccionado.descuentos_judiciales) || 0;

  // Usar tu lógica actual (PlanillaConstants)
  const calculos = PlanillaConstants.calcularDescuentosEmpleado(
    salarioDevengado,
    montoHorasExtra,
    comisiones,
    bonificaciones,
    otrosIngresos,
    this.planilla.tipo_planilla
  );

  // Asignar valores calculados
  this.detalleSeleccionado.total_ingresos = calculos.totalIngresos;
  this.detalleSeleccionado.isss_empleado = calculos.isssEmpleado;
  this.detalleSeleccionado.isss_patronal = calculos.isssPatronal;
  this.detalleSeleccionado.afp_empleado = calculos.afpEmpleado;
  this.detalleSeleccionado.afp_patronal = calculos.afpPatronal;
  this.detalleSeleccionado.renta = calculos.renta;

  // Calcular total de descuentos
  const totalDescuentos = calculos.isssEmpleado + calculos.afpEmpleado + calculos.renta +
    prestamos + anticipos + otrosDescuentos + descuentosJudiciales;

  this.detalleSeleccionado.total_descuentos = this.round(totalDescuentos);
  this.detalleSeleccionado.sueldo_neto = this.round(calculos.totalIngresos - totalDescuentos);

  // Recalcular totales de planilla
  this.calcularTotalesPlanilla();
  
  console.log('⚠️ Usando sistema legacy');
}

// public calcularTotalesPlanilla() {
//   // Inicializar totales
//   this.planilla.total_salarios = 0;
//   this.planilla.bonificaciones_total = 0;
//   this.planilla.comisiones_total = 0;
//   this.planilla.total_ingresos = 0;
//   this.planilla.total_iss = 0;
//   this.planilla.total_afp = 0;
//   this.planilla.total_isr = 0;
//   this.planilla.total_neto = 0;

//   const detallesActivos = this.detalles?.filter(d => d.estado !== 0);
//   if (!detallesActivos?.length) {
//     this.notValue = true;
//     return;
//   }

//   this.notValue = false;

//   detallesActivos.forEach((detalle) => {
//     // ✅ USAR VALORES DEL BACKEND
//     this.planilla.total_salarios += Number(detalle.salario_devengado) || 0;
//     this.planilla.bonificaciones_total += Number(detalle.bonificaciones) || 0;
//     this.planilla.comisiones_total += Number(detalle.comisiones) || 0;
//     this.planilla.total_ingresos += Number(detalle.total_ingresos) || 0;
//     this.planilla.total_iss += Number(detalle.isss_empleado) || 0;
//     this.planilla.total_afp += Number(detalle.afp_empleado) || 0;
//     this.planilla.total_isr += Number(detalle.renta) || 0;
//     this.planilla.total_neto += Number(detalle.sueldo_neto) || 0;
//   });

//   // Redondear totales
//   Object.keys(this.planilla).forEach(key => {
//     if (typeof this.planilla[key] === 'number') {
//       this.planilla[key] = Math.round(this.planilla[key] * 100) / 100;
//     }
//   });
// }

public calcularTotalesPlanilla(): void {
  if (!this.detalles?.length || !this.planilla) {
    this.notValue = true;
    return;
  }

  // Resetear totales
  this.resetearTotalesPlanilla();

  const detallesActivos = this.detalles.filter(d => d.estado !== 0);
  if (!detallesActivos.length) {
    this.notValue = true;
    return;
  }

  this.notValue = false;

  // Calcular totales usando valores del backend/sistema configurable
  detallesActivos.forEach((detalle) => {
    this.planilla.total_salarios += Number(detalle.salario_devengado) || 0;
    this.planilla.bonificaciones_total += Number(detalle.bonificaciones) || 0;
    this.planilla.comisiones_total += Number(detalle.comisiones) || 0;
    this.planilla.total_ingresos += Number(detalle.total_ingresos) || 0;
    this.planilla.total_iss += Number(detalle.isss_empleado) || 0;
    this.planilla.total_afp += Number(detalle.afp_empleado) || 0;
    this.planilla.total_isr += Number(detalle.renta) || 0;
    this.planilla.total_neto += Number(detalle.sueldo_neto) || 0;
  });

  // Redondear totales
  this.roundTotalesPlanilla();
}

private resetearTotalesPlanilla(): void {
  this.planilla.total_salarios = 0;
  this.planilla.bonificaciones_total = 0;
  this.planilla.comisiones_total = 0;
  this.planilla.total_ingresos = 0;
  this.planilla.total_iss = 0;
  this.planilla.total_afp = 0;
  this.planilla.total_isr = 0;
  this.planilla.total_neto = 0;
}

private roundTotalesPlanilla(): void {
  Object.keys(this.planilla).forEach(key => {
    if (typeof this.planilla[key] === 'number') {
      this.planilla[key] = this.round(this.planilla[key]);
    }
  });
}

public compararSistemas(detalle: any): void {
  if (!detalle) return;

  const salarioDevengado = Number(detalle.salario_devengado) || 0;
  
  // Sistema legacy
  const calculosLegacy = PlanillaConstants.calcularDescuentosEmpleado(
    salarioDevengado, 0, 0, 0, 0, this.planilla.tipo_planilla
  );

  // Datos para sistema configurable
  const datosEmpleado = {
    salario_base: Number(detalle.salario_base) || 0,
    salario_devengado: salarioDevengado,
    tipo_planilla: this.planilla.tipo_planilla
  };

  this.configPlanillaService.probarCalculo(datosEmpleado).subscribe({
    next: (resultado) => {
      console.table({
        'Concepto': ['ISSS Empleado', 'AFP Empleado', 'Renta', 'Sueldo Neto'],
        'Sistema Legacy': [
          calculosLegacy.isssEmpleado,
          calculosLegacy.afpEmpleado, 
          calculosLegacy.renta,
          calculosLegacy.totalIngresos - calculosLegacy.isssEmpleado - calculosLegacy.afpEmpleado - calculosLegacy.renta
        ],
        'Sistema Configurable': [
          resultado.resultados.isss_empleado,
          resultado.resultados.afp_empleado,
          resultado.resultados.renta,
          resultado.resultados.totales.sueldo_neto
        ]
      });
    },
    error: (error) => {
      console.error('Error comparando sistemas:', error);
    }
  });
}

public recalcularDetalle(detalle: any): void {
  const detalleAnterior = this.detalleSeleccionado;
  this.detalleSeleccionado = detalle;
  this.calcularDescuentos();
  this.detalleSeleccionado = detalleAnterior;
}

public validarConfiguracionEmpresa(): void {
  this.configPlanillaService.obtenerConfiguracion().subscribe({
    next: (config) => {
      console.log('✅ Configuración de empresa cargada:', config);
      if (config.configuracion.conceptos) {
        const totalConceptos = Object.keys(config.configuracion.conceptos).length;
        console.log(`📊 Total conceptos configurados: ${totalConceptos}`);
      }
    },
    error: (error) => {
      console.warn('⚠️ Empresa sin configuración personalizada, usando sistema legacy');
    }
  });
}




}
