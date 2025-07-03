// planilla-detalle.component.ts
import { Component, OnInit, TemplateRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaConstants } from '../../../constants/planilla.constants';

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

  modalRef!: BsModalRef;
  detalleSeleccionado: any = null;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

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
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      },
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

  public calcularTotales() {
    if (!this.detalleSeleccionado) return;

    // Obtener el salario base mensual
    const salarioBaseMensual =
      parseFloat(this.detalleSeleccionado.salario_base) || 0;

    // Ajustar el salario base según el tipo de planilla
    let salarioBaseAjustado = salarioBaseMensual;
    let diasReferencia = 30; // Por defecto, mensual

    if (this.planilla.tipo_planilla === 'quincenal') {
      // Si es quincenal, el salario base para cálculos es la mitad del mensual
      salarioBaseAjustado = salarioBaseMensual / 2;
      diasReferencia = 15;
    } else if (this.planilla.tipo_planilla === 'semanal') {
      // Si es semanal, el salario base es una cuarta parte del mensual aproximadamente
      salarioBaseAjustado = salarioBaseMensual / 4.33; // 4.33 semanas por mes en promedio
      diasReferencia = 7;
    }

    const diasLaborados =
      parseFloat(this.detalleSeleccionado.dias_laborados) || diasReferencia;

    // Calcular salario devengado según días trabajados
    const salarioDevengado =
      (salarioBaseAjustado / diasReferencia) * diasLaborados;
    this.detalleSeleccionado.salario_devengado = Number(
      salarioDevengado.toFixed(2)
    );

    const horasExtra = parseFloat(this.detalleSeleccionado.horas_extra) || 0;
    const comisiones = parseFloat(this.detalleSeleccionado.comisiones) || 0;
    const bonificaciones =
      parseFloat(this.detalleSeleccionado.bonificaciones) || 0;
    const otrosIngresos =
      parseFloat(this.detalleSeleccionado.otros_ingresos) || 0;
    const prestamos = parseFloat(this.detalleSeleccionado.prestamos) || 0;
    const anticipos = parseFloat(this.detalleSeleccionado.anticipos) || 0;
    const otrosDescuentos =
      parseFloat(this.detalleSeleccionado.otros_descuentos) || 0;
    const descuentosJudiciales =
      parseFloat(this.detalleSeleccionado.descuentos_judiciales) || 0;

    let montoHorasExtra = 0;
    if (horasExtra > 0) {
      const valorHoraNormal = salarioBaseAjustado / diasReferencia / 8;
      montoHorasExtra = Number(
        (horasExtra * (valorHoraNormal * 1.25)).toFixed(2)
      );
    }
    this.detalleSeleccionado.monto_horas_extra = montoHorasExtra;

    const totalIngresos = Number(
      (
        salarioDevengado +
        montoHorasExtra +
        comisiones +
        bonificaciones +
        otrosIngresos
      ).toFixed(2)
    );
    this.detalleSeleccionado.total_ingresos = totalIngresos;

    // Calcular ISSS (3%) - tope $1000
    const baseISSSEmpleado = Math.min(totalIngresos, 1000);
    this.detalleSeleccionado.isss_empleado = Number(
      (baseISSSEmpleado * 0.03).toFixed(2)
    );

    // Calcular ISSS patronal (7.5%) - tope $1000
    this.detalleSeleccionado.isss_patronal = Number(
      (baseISSSEmpleado * 0.075).toFixed(2)
    );

    // Calcular AFP empleado (7.25%)
    this.detalleSeleccionado.afp_empleado = Number(
      (totalIngresos * 0.0725).toFixed(2)
    );

    // Calcular AFP patronal (7.75%)
    this.detalleSeleccionado.afp_patronal = Number(
      (totalIngresos * 0.0775).toFixed(2)
    );

    // Calcular base para renta
    const baseRenta = Number(
      (
        totalIngresos -
        this.detalleSeleccionado.isss_empleado -
        this.detalleSeleccionado.afp_empleado
      ).toFixed(2)
    );

    // Calcular ISR según tabla de El Salvador
    let renta = 0;

    // Ajustar el cálculo de renta según tipo de planilla
    let baseRentaAnualizada = baseRenta;
    let factorAjuste = 1;

    if (this.planilla.tipo_planilla === 'quincenal') {
      // Para quincena, multiplicamos por 2 para obtener el valor mensual equivalente
      factorAjuste = 2;
      baseRentaAnualizada = baseRenta * factorAjuste;
    } else if (this.planilla.tipo_planilla === 'semanal') {
      // Para semanal, multiplicamos por 4.33 para obtener el valor mensual equivalente
      factorAjuste = 4.33;
      baseRentaAnualizada = baseRenta * factorAjuste;
    }

    // Aplicar tabla de renta
    if (baseRentaAnualizada <= 472.0) {
      renta = 0;
    } else if (baseRentaAnualizada <= 895.24) {
      renta = (baseRentaAnualizada - 472.0) * 0.1 + 17.67;
    } else if (baseRentaAnualizada <= 2038.1) {
      renta = (baseRentaAnualizada - 895.24) * 0.2 + 60.0;
    } else {
      renta = (baseRentaAnualizada - 2038.1) * 0.3 + 288.57;
    }

    // Ajustamos el resultado según el tipo de planilla
    if (this.planilla.tipo_planilla !== 'mensual') {
      renta = renta / factorAjuste;
    }

    this.detalleSeleccionado.renta = Number(renta.toFixed(2));

    // Calcular total de descuentos
    const totalDescuentos = Number(
      (
        this.detalleSeleccionado.isss_empleado +
        this.detalleSeleccionado.afp_empleado +
        this.detalleSeleccionado.renta +
        prestamos +
        anticipos +
        otrosDescuentos +
        descuentosJudiciales
      ).toFixed(2)
    );
    this.detalleSeleccionado.total_descuentos = totalDescuentos;

    // Calcular sueldo neto
    this.detalleSeleccionado.sueldo_neto = Number(
      (totalIngresos - totalDescuentos).toFixed(2)
    );
  }

  public calcularTotalesPlanilla() {
    // Inicializar totales
    this.planilla.total_salarios = 0;
    this.planilla.bonificaciones_total = 0;
    this.planilla.comisiones_total = 0;
    this.planilla.total_ingresos = 0;
    this.planilla.total_iss = 0; // Solo ISSS empleado
    this.planilla.total_afp = 0; // Solo AFP empleado
    this.planilla.total_isr = 0;
    this.planilla.total_neto = 0;

    // Filtrar solo los detalles activos
    const detallesActivos = this.detalles?.filter(
      (detalle) => detalle.estado !== 0
    );

    if (!detallesActivos || detallesActivos.length === 0) {
      this.notValue = true;
      return;
    }

    this.notValue = false;

    // Determinar valores según tipo de planilla
    let diasReferencia = 30; // Por defecto, mensual
    let factorAjuste = 1;

    if (this.planilla.tipo_planilla === 'quincenal') {
      diasReferencia = 15;
      factorAjuste = 2; // 2 quincenas por mes
    } else if (this.planilla.tipo_planilla === 'semanal') {
      diasReferencia = 7;
      factorAjuste = 4.33; // ~4.33 semanas por mes
    }

    detallesActivos.forEach((detalle) => {
      const salarioBase = Number(detalle.salario_base) || 0;
      const salarioBaseAjustado =
        this.planilla.tipo_planilla !== 'mensual'
          ? salarioBase / factorAjuste
          : salarioBase;

      const diasLaborados = Number(detalle.dias_laborados) || diasReferencia;

      // Calcular salario devengado según días laborados
      const salarioDevengado =
        (salarioBaseAjustado / diasReferencia) * diasLaborados;

      // Acumular salarios base (en realidad es el salario devengado)
      this.planilla.total_salarios += salarioDevengado;

      // Bonificaciones
      this.planilla.bonificaciones_total += Number(detalle.bonificaciones) || 0;

      // Comisiones
      this.planilla.comisiones_total += Number(detalle.comisiones) || 0;

      // Total ingresos (incluye salario devengado, horas extra, bonificaciones, comisiones y otros ingresos)
      const totalIngresosEmpleado =
        salarioDevengado +
          Number(detalle.monto_horas_extra) +
          Number(detalle.bonificaciones) +
          Number(detalle.comisiones) +
          Number(detalle.otros_ingresos) || 0;

      this.planilla.total_ingresos += totalIngresosEmpleado;

      // ISSS (solo empleado)
      const baseISSSEmpleado = Math.min(totalIngresosEmpleado, 1000);
      const isssEmpleado = baseISSSEmpleado * 0.03;
      this.planilla.total_iss += isssEmpleado;

      // AFP (solo empleado)
      const afpEmpleado = totalIngresosEmpleado * 0.0725;
      this.planilla.total_afp += afpEmpleado;

      // ISR (Renta)
      const baseRenta = totalIngresosEmpleado - isssEmpleado - afpEmpleado;

      // Ajustar el cálculo de renta según tipo de planilla
      let baseRentaAnualizada = baseRenta;

      if (this.planilla.tipo_planilla !== 'mensual') {
        // Multiplicamos por el factor de ajuste para obtener el valor mensual equivalente
        baseRentaAnualizada = baseRenta * factorAjuste;
      }

      let renta = 0;
      if (baseRentaAnualizada <= 472.0) {
        renta = 0;
      } else if (baseRentaAnualizada <= 895.24) {
        renta = (baseRentaAnualizada - 472.0) * 0.1 + 17.67;
      } else if (baseRentaAnualizada <= 2038.1) {
        renta = (baseRentaAnualizada - 895.24) * 0.2 + 60.0;
      } else {
        renta = (baseRentaAnualizada - 2038.1) * 0.3 + 288.57;
      }

      // Ajustamos el resultado según el tipo de planilla
      if (this.planilla.tipo_planilla !== 'mensual') {
        renta = renta / factorAjuste;
      }

      this.planilla.total_isr += renta;

      // Total Neto (después de todas las deducciones)
      const totalDescuentos =
        isssEmpleado +
          afpEmpleado +
          renta +
          Number(detalle.prestamos) +
          Number(detalle.anticipos) +
          Number(detalle.otros_descuentos) +
          Number(detalle.descuentos_judiciales) || 0;

      this.planilla.total_neto += totalIngresosEmpleado - totalDescuentos;
    });

    // Redondear todos los totales a 2 decimales
    this.planilla.total_salarios = Number(
      this.planilla.total_salarios.toFixed(2)
    );
    this.planilla.bonificaciones_total = Number(
      this.planilla.bonificaciones_total.toFixed(2)
    );
    this.planilla.comisiones_total = Number(
      this.planilla.comisiones_total.toFixed(2)
    );
    this.planilla.total_ingresos = Number(
      this.planilla.total_ingresos.toFixed(2)
    );
    this.planilla.total_iss = Number(this.planilla.total_iss.toFixed(2));
    this.planilla.total_afp = Number(this.planilla.total_afp.toFixed(2));
    this.planilla.total_isr = Number(this.planilla.total_isr.toFixed(2));
    this.planilla.total_neto = Number(this.planilla.total_neto.toFixed(2));
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
}
