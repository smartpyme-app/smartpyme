import {Component, OnInit, TemplateRef, ViewChild} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import {BsModalRef} from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import {AlertService} from '@services/alert.service';
import {ApiService} from '@services/api.service';
import {PlanillaConstants} from '../../constants/planilla.constants';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-planillas',
    templateUrl: './planillas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PopoverModule, TooltipModule, PaginationComponent],

})
export class PlanillasComponent extends BasePaginatedModalComponent implements OnInit {
  public planillas: PaginatedResponse<any> = {} as PaginatedResponse;
  public planilla: any = {};
  public procesando: boolean = false;
  public planillaEdit: any = {};
  public modoDuplicacion: boolean = false;

  public editando: boolean = false;

  public override filtros: any = {
    anio: '',
    mes: '',
    tipo_planilla: '',
    estado: '',
    buscador: '',
    paginate: 10,
    orden: 'created_at',
    direccion: 'desc',
  };

  public datosImportacion = {
    fecha_inicio: '',
    fecha_fin: '',
    tipo_planilla: '',
    archivo: null as File | null,
  };

  public archivoSeleccionado: boolean = false;
  public procesandoImportacion = false;
  public procesandoExportacion = false;
  public filtrosExportar = {
    fecha_inicio: '',
    fecha_fin: '',
    tipo_planilla: '',
    estado: '',
  };

  public usuario: any = {};
  public periodos: any[] = [];

  @ViewChild('mNuevaPlanilla') mNuevaPlanilla!: TemplateRef<any>;

  constructor(
    apiService: ApiService,
    alertService: AlertService,
    modalManager: ModalManagerService
  ) {
    super(apiService, alertService, modalManager);
    this.generarPeriodos();
  }

  protected getPaginatedData(): PaginatedResponse | null {
    return this.planillas;
  }

  protected setPaginatedData(data: PaginatedResponse): void {
    this.planillas = data;
  }

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.loadPlanillas();
  }

  private generarPeriodos() {
    const currentYear = new Date().getFullYear();
    this.periodos = [];

    // Generar últimos 2 años
    for (let year = currentYear; year >= currentYear - 1; year--) {
      for (let month = 12; month >= 1; month--) {
        if (year === currentYear && month > new Date().getMonth() + 1) continue;

        this.periodos.push({
          anio: year,
          mes: month,
          nombre: `${this.getNombreMes(month)} ${year}`,
        });
      }
    }
  }

  private getNombreMes(mes: number): string {
    const meses = [
      'Enero',
      'Febrero',
      'Marzo',
      'Abril',
      'Mayo',
      'Junio',
      'Julio',
      'Agosto',
      'Septiembre',
      'Octubre',
      'Noviembre',
      'Diciembre',
    ];
    return meses[mes - 1];
  }

  public loadPlanillas() {
    this.loading = true;
    this.apiService.getAll('planillas', this.filtros)
        .pipe(this.untilDestroyed())
        .subscribe({
      next: (planillas) => {
        this.planillas = planillas;
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      },
    });
  }

  public filtrarPlanillas() {
    this.loadPlanillas();
    if (this.modalRef) {
      this.closeModal();
    }
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.filtrarPlanillas();
  }

  // setPagination() ahora se hereda de BasePaginatedComponent

  public openFilter(template: TemplateRef<any>) {
    super.openModal(template);
  }

  public openNuevaPlanilla(
    template: TemplateRef<any>,
    planillaParaDuplicar?: any
  ) {
    this.modoDuplicacion = !!planillaParaDuplicar;

    this.planilla = {
      fecha_inicio: '',
      fecha_fin: '',
      tipo_planilla: 'quincenal',
      planillaTemplate: planillaParaDuplicar ? planillaParaDuplicar.id : null,
    };

    super.openModal(template);
  }

  public generarPlanilla() {
    if (
      !this.planilla.fecha_inicio ||
      !this.planilla.fecha_fin ||
      !this.planilla.tipo_planilla
    ) {
      this.alertService.error('Por favor complete todos los campos');
      return;
    }

    this.saving = true;
    this.apiService.store('planillas/generate', this.planilla)
        .pipe(this.untilDestroyed())
        .subscribe({
      next: (response) => {
        this.alertService.success('Exito', 'Planilla generada exitosamente');
        this.loadPlanillas();
        this.closeModal();
        this.saving = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.saving = false;
      },
    });
  }

  public aprobarPlanilla(planilla: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: 'Una vez aprobada la planilla no podrá modificarla',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, aprobar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.procesando = true;

        this.apiService
          .store(`planillas/aprobar/${planilla.id}`, {})
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (response) => {
              this.alertService.success(
                'Éxito',
                'Planilla aprobada exitosamente'
              );
              this.loadPlanillas();
              this.procesando = false;
            },
            error: (error) => {
              this.alertService.error(error);
              this.procesando = false;
            },
          });
      }
    });
  }

  public revertirPlanilla(planilla: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: 'La planilla se revertirá a estado borrador',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, revertir',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.procesando = true;
        this.apiService.store(`planillas/revertir/${planilla.id}`, {})
            .pipe(this.untilDestroyed())
            .subscribe({
          next: (response) => {
            this.alertService.success('Éxito', 'Planilla revertida exitosamente');
            this.loadPlanillas();
            this.procesando = false;
          },
          error: (error) => {
            this.alertService.error(error);
            this.procesando = false;
          },
        });
      }
    });
  }

  public procesarPago(planilla: any) {
    Swal.fire({
      title: '¿Procesar pago de planilla?',
      text: `¿Está seguro de procesar el pago de la planilla ${planilla.codigo}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, procesar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
    }).then((result) => {
      if (result.isConfirmed) {
        this.procesando = true;

        // Llamar al backend para procesar el pago
        this.apiService.store(`planillas/${planilla.id}/pagar`, {})
            .pipe(this.untilDestroyed())
            .subscribe({
          next: (response) => {
            Swal.fire({
              title: '¡Éxito!',
              text: 'Pago procesado y registros contables generados correctamente',
              icon: 'success',
            });
            this.loadPlanillas();
            this.procesando = false;
          },
          error: (error) => {
            Swal.fire({
              title: 'Error',
              text: 'Ocurrió un error al procesar el pago',
              icon: 'error',
            });
            this.alertService.error(error);
            this.procesando = false;
          },
        });
      }
    });
  }

  public getEstadoPlanilla(estado: Number): string {
    switch (estado) {
      case PlanillaConstants.ESTADOS_PLANILLA.INACTIVA:
        return 'Inactiva';
      case PlanillaConstants.ESTADOS_PLANILLA.ACTIVA:
        return 'Activa';
      case PlanillaConstants.ESTADOS_PLANILLA.BORRADOR:
        return 'Borrador';
      case PlanillaConstants.ESTADOS_PLANILLA.APROBADA:
        return 'Aprobada';
      case PlanillaConstants.ESTADOS_PLANILLA.PENDIENTE:
        return 'Pendiente';
      case PlanillaConstants.ESTADOS_PLANILLA.PAGADA:
        return 'Pagada';
      case PlanillaConstants.ESTADOS_PLANILLA.ANULADA:
        return 'Anulada';
      default:
        return 'Desconocido';
    }
  }

  public getEstadoClass(estado: Number): string {
    switch (estado) {
      case PlanillaConstants.ESTADOS_PLANILLA.INACTIVA:
        return 'bg-dark';
      case PlanillaConstants.ESTADOS_PLANILLA.ACTIVA:
        return 'bg-success';
      case PlanillaConstants.ESTADOS_PLANILLA.BORRADOR:
        return 'bg-secondary';
      case PlanillaConstants.ESTADOS_PLANILLA.APROBADA:
        return 'bg-success';
      case PlanillaConstants.ESTADOS_PLANILLA.PENDIENTE:
        return 'bg-secondary';
      case PlanillaConstants.ESTADOS_PLANILLA.PAGADA:
        return 'bg-info';
      case PlanillaConstants.ESTADOS_PLANILLA.ANULADA:
        return 'bg-danger';
      default:
        return 'bg-secondary';
    }
  }

  public puedeEliminarPlanilla(planilla: any): boolean {
    // Por ahora usamos directamente el valor 2 (PLANILLA_BORRADOR) hasta verificar las constantes dinámicas
    return planilla.estado === 2;
  }

  public eliminarPlanilla(planilla: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: `¿Desea eliminar la planilla ${planilla.codigo}? Esta acción no se puede deshacer.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.procesando = true;

        this.apiService.delete('planillas/', planilla.id)
            .pipe(this.untilDestroyed())
            .subscribe({
          next: (response) => {
            this.alertService.success('Éxito', 'Planilla eliminada exitosamente');
            this.loadPlanillas();
            this.procesando = false;
          },
          error: (error) => {
            this.alertService.error(error);
            this.procesando = false;
          },
        });
      }
    });
  }

  public generarBoletas(planilla: any) {
    this.procesando = true;

    this.apiService.generatePayrollSlips(planilla.id)
        .pipe(this.untilDestroyed())
        .subscribe({
      next: (response: Blob) => {
        const filename = `boletas_planilla_${planilla.codigo}.pdf`;
        this.apiService.downloadFile(response, filename);
        this.alertService.success('Éxito', 'Boletas generadas exitosamente');
        this.procesando = false;
      },
      error: (error) => {
        this.alertService.error(
          'Error al generar las boletas: ' + error.message
        );
        this.procesando = false;
      },
    });
  }

  public enviarBoletas(planilla: any) {
    if (
      !confirm(
        '¿Está seguro de enviar las boletas por correo a todos los empleados?'
      )
    ) {
      return;
    }

    this.procesando = true;
    this.apiService
      .store(`planillas/${planilla.id}/enviar-boletas`, {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          this.alertService.success('Exito', 'Boletas enviadas exitosamente');
          this.procesando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.procesando = false;
        },
      });
  }

  public enviarBoletasPorEmail() {
    if (this.planilla.estado !== PlanillaConstants.ESTADOS_PLANILLA.APROBADA) {
      this.alertService.warning(
        'Advertencia',
        'La planilla debe estar aprobada para enviar las boletas'
      );
      return;
    }

    this.procesando = true;
    this.apiService
      .store(`planillas/${this.planilla.id}/enviar-boletas`, {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.alertService.success('Éxito', 'Boletas enviadas exitosamente');
          this.procesando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.procesando = false;
        },
      });
  }

  public exportarExcel(planilla: any) {
    this.procesando = true;
    this.apiService.download(`planillas/${planilla.id}/excel`)
        .pipe(this.untilDestroyed())
        .subscribe({
      next: (response) => {
        const blob = new Blob([response], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `planilla_${planilla.codigo}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.procesando = false;
      },
      error: (error) => {
        this.alertService.error('Error al exportar la planilla a Excel');
        this.procesando = false;
      },
    });
  }

  public exportarPDF(planilla: any) {
    this.procesando = true;
    this.apiService.download(`planillas/${planilla.id}/pdf`)
        .pipe(this.untilDestroyed())
        .subscribe({
      next: (response) => {
        const blob = new Blob([response], {type: 'application/pdf'});
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `planilla_${planilla.codigo}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.procesando = false;
      },
      error: (error) => {
        this.alertService.error('Error al exportar la planilla a PDF');
        this.procesando = false;
      },
    });
  }

  public openEditarPlanilla(planilla: any, template: TemplateRef<any>) {
    this.planillaEdit = {
      ...planilla,
      fecha_inicio: this.formatearFecha(planilla.fecha_inicio),
      fecha_fin: this.formatearFecha(planilla.fecha_fin),
    };

    super.openLargeModal(template);
  }

  private formatearFecha(fecha: string): string {
    if (!fecha) return '';
    // Convertir la fecha a formato local
    const date = new Date(fecha);
    // Ajustar por timezone
    date.setMinutes(date.getMinutes() + date.getTimezoneOffset());
    return date.toISOString().split('T')[0];
  }

  public actualizarPlanilla() {
    if (!this.validarPlanilla()) {
      return;
    }

    const planillaToUpdate = {
      ...this.planillaEdit,
      fecha_inicio: new Date(this.planillaEdit.fecha_inicio).toISOString(),
      fecha_fin: new Date(this.planillaEdit.fecha_fin).toISOString(),
    };

    this.saving = true;
    this.apiService
      .store(`planillas/update/${this.planillaEdit.id}`, planillaToUpdate)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          this.alertService.success(
            'Éxito',
            'Planilla actualizada exitosamente'
          );
          this.loadPlanillas();
          this.closeModal();
          this.saving = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.saving = false;
        },
      });
  }

  private validarPlanilla(): boolean {
    if (!this.planillaEdit.fecha_inicio || !this.planillaEdit.fecha_fin) {
      this.alertService.error('Las fechas son requeridas');
      return false;
    }

    const fechaInicio = new Date(this.planillaEdit.fecha_inicio);
    const fechaFin = new Date(this.planillaEdit.fecha_fin);

    if (fechaFin < fechaInicio) {
      this.alertService.error(
        'La fecha de fin debe ser posterior a la fecha de inicio'
      );
      return false;
    }

    return true;
  }

  public duplicarPlanilla(planilla: any, template: TemplateRef<any>) {
    this.openNuevaPlanilla(template, planilla);
  }

  public descargarPlanillas() {
    this.apiService.download('planillas/generar');
  }

  override openModal(template: TemplateRef<any>) {
    super.openModal(template);
  }

  onFileSelected(event: any) {
    if (event.target.files.length > 0) {
      const file = event.target.files[0];
      if (this.validarArchivo(file)) {
        this.datosImportacion.archivo = file;
        this.archivoSeleccionado = true;
      }
    }
  }

  esFormularioValido(): boolean {
    return (
      !!this.datosImportacion.fecha_inicio &&
      !!this.datosImportacion.fecha_fin &&
      this.archivoSeleccionado &&
      this.esRangoFechasValido() &&
      !this.procesandoImportacion
    );
  }

  esRangoFechasValido(): boolean {
    if (
      !this.datosImportacion.fecha_inicio ||
      !this.datosImportacion.fecha_fin
    ) {
      return true; // No validamos si no hay fechas
    }

    const fechaInicio = new Date(this.datosImportacion.fecha_inicio);
    const fechaFin = new Date(this.datosImportacion.fecha_fin);

    return fechaFin >= fechaInicio;
  }

  validarArchivo(file: File): boolean {
    // Validar tipo de archivo
    const tiposPermitidos = [
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    if (!tiposPermitidos.includes(file.type)) {
      this.alertService.error(
        'El archivo debe ser un documento de Excel (.xlsx, .xls)'
      );
      return false;
    }

    // Validar tamaño (ejemplo: máximo 5MB)
    const tamanoMaximo = 5 * 1024 * 1024; // 5MB en bytes
    if (file.size > tamanoMaximo) {
      this.alertService.error('El archivo no debe superar los 5MB');
      return false;
    }

    return true;
  }

  descargarPlantilla() {
    this.apiService.download('planillas/plantilla-importacion')
        .pipe(this.untilDestroyed())
        .subscribe(
      (response: any) => {
        const blob = new Blob([response], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'plantilla_importacion_planillas.xlsx';
        link.click();
        window.URL.revokeObjectURL(url);
      },
      (error) => {
        this.alertService.error('Error al descargar la plantilla');
      }
    );
  }

  importarPlanillas() {
    if (!this.esFormularioValido()) {
      return;
    }

    this.procesandoImportacion = true;
    const formData = new FormData();
    formData.append('archivo', this.datosImportacion.archivo!);
    formData.append('fecha_inicio', this.datosImportacion.fecha_inicio);
    formData.append('fecha_fin', this.datosImportacion.fecha_fin);
    formData.append('tipo_planilla', this.datosImportacion.tipo_planilla);

    this.apiService.store('planillas/importar', formData)
        .pipe(this.untilDestroyed())
        .subscribe({
      next: (response) => {
        this.alertService.success(
          'Éxito',
          'Planillas importadas correctamente'
        );
        this.closeModal();
        this.loadPlanillas(); // Recargar la lista de planillas
        this.procesandoImportacion = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.procesandoImportacion = false;
      },
    });
  }

  // Método para exportar planillas
  exportarPlanillas() {
    this.procesandoExportacion = true;

    this.apiService
      .exportAcumulado('planillas/exportar', this.filtrosExportar)
      .pipe(this.untilDestroyed())
      .subscribe(
        (response: Blob) => {
          const blob = new Blob([response], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          });
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = `planillas_${new Date().getTime()}.xlsx`;
          link.click();
          window.URL.revokeObjectURL(url);
          this.closeModal();
          this.procesandoExportacion = false;
        },
        (error) => {
          this.alertService.error('Error al exportar las planillas');
          this.procesandoExportacion = false;
        }
      );
  }
}

