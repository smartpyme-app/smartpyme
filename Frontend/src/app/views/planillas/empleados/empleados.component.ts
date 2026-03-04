import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaConstants } from '../../../constants/planilla.constants';

@Component({
  selector: 'app-empleados',
  templateUrl: './empleados.component.html',
})
export class EmpleadosComponent implements OnInit {
  public empleados: any = [];
  public empleado: any = {};
  public loading: boolean = false;
  public saving: boolean = false;

  public departamentos: any = [];
  public cargos: any = [];
  public filtros: any = {};

  public datosImportacion = {
    archivo: null as File | null,
  };

  public procesandoImportacion = false;

  modalRef!: BsModalRef;

  // Expose enum to template
  ESTADO_EMPLEADO = PlanillaConstants.ESTADOS_EMPLEADO;
  

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.loadEmpleados();
    this.loadCatalogos();
  }
  
  public loadAll() {
    // Inicializar filtros
    this.filtros = {
      estado: '',
      id_departamento: '',
      id_cargo: '',
      tipo_contrato: '',
      tipo_jornada: '',
      buscador: '',
      orden: 'nombres',
      direccion: 'asc',
      paginate: 10,
    };

    this.loadEmpleados();
    this.loadCatalogos();

    if (this.modalRef) {
      this.modalRef.hide();
    }
  }

  public loadEmpleados() {
    this.loading = true;
    this.apiService.getAll('empleados', this.filtros).subscribe(
      (empleados) => {
        this.empleados = empleados;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public loadCatalogos() {
    this.apiService.getAll('departamentosPlanilla/list').subscribe(
      (departamentos) => {
        this.departamentos = departamentos;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('cargos/list').subscribe(
      (cargos) => {
        this.cargos = cargos;
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public filtrarEmpleados() {
    this.loadEmpleados();
    if (this.modalRef) {
      this.modalRef.hide();
    }
  }

  public cambiarEstado(empleado: any, estado: string) {
    if (!confirm('¿Está seguro de cambiar el estado del empleado?')) {
      return;
    }

    this.saving = true;
    const empleadoActualizado = { ...empleado, estado };

    this.apiService.store('empleados', empleadoActualizado).subscribe({
      next: () => {
        this.saving = false;
        this.alertService.success(
          'Estado actualizado correctamente',
          'Empleado'
        );
        this.loadEmpleados();
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
    this.loadEmpleados();
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService
      .paginate(this.empleados.path + '?page=' + event.page, this.filtros)
      .subscribe({
        next: (empleados) => {
          this.empleados = empleados;
          this.loading = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.loading = false;
        },
      });
  }

  public openFilter(template: TemplateRef<any>) {
    this.modalRef = this.modalService.show(template);
  }

  public openModal(template: TemplateRef<any>) {
    this.datosImportacion.archivo = null;
    this.modalRef = this.modalService.show(template);
  }

  public getEstadoClass(estado: number | string): string {
    const e = typeof estado === 'string' ? parseInt(estado, 10) : estado;
    if (e === PlanillaConstants.ESTADOS_EMPLEADO.ACTIVO) return 'bg-success';
    if (e === PlanillaConstants.ESTADOS_EMPLEADO.INACTIVO) return 'bg-danger';
    if (e === PlanillaConstants.ESTADOS_EMPLEADO.VACACIONES) return 'bg-info';
    if (e === PlanillaConstants.ESTADOS_EMPLEADO.INCAPACIDAD) return 'bg-warning';
    if (e === PlanillaConstants.ESTADOS_EMPLEADO.SUSPENDIDO) return 'bg-secondary';
    return 'bg-secondary';
  }

  public getNombreEstadoEmpleado(estado: number | string): string {
    return PlanillaConstants.getNombreEstadoEmpleado(estado);
  }

  public openModalDarBaja(template: TemplateRef<any>, empleado: any) {
    this.empleado = { ...empleado };
    // Establecer la fecha de notificación por defecto como hoy
    this.empleado.fecha_fin = new Date().toISOString().split('T')[0];
    // La fecha efectiva de baja puede ser igual a la fecha de notificación inicialmente
    this.empleado.fecha_baja = this.empleado.fecha_fin;
    this.modalRef = this.modalService.show(template);
  }

  public onFileSelected(event: any) {
    if (event.target.files.length > 0) {
      const file = event.target.files[0];
      if (file.size > 2 * 1024 * 1024) {
        // 2MB
        this.alertService.error('El archivo no puede ser mayor a 2MB');
        event.target.value = '';
        return;
      }
      this.empleado.documento_respaldo = file;
    }
  }

  public onFileSelectedImport(event: any) {
    if (event.target.files.length > 0) {
      const file = event.target.files[0];
      // Validar tipo de archivo
      const allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
      ];
      if (!allowedTypes.includes(file.type)) {
        this.alertService.error(
          'Por favor seleccione un archivo Excel válido (.xlsx o .xls)'
        );
        event.target.value = '';
        return;
      }
      // Validar tamaño (10MB máximo)
      if (file.size > 10 * 1024 * 1024) {
        this.alertService.error('El archivo no puede ser mayor a 10MB');
        event.target.value = '';
        return;
      }
      this.datosImportacion.archivo = file;
    }
  }

  public descargarPlantilla() {
    this.apiService.download('planillas/plantilla-importacion').subscribe(
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
      (error: any) => {
        this.alertService.error('Error al descargar la plantilla');
      }
    );
  }

  public importarEmpleados() {
    if (!this.datosImportacion.archivo) {
      this.alertService.error('Por favor seleccione un archivo Excel');
      return;
    }

    this.procesandoImportacion = true;
    const formData = new FormData();
    formData.append('archivo', this.datosImportacion.archivo);

    this.apiService.store('empleados/importar', formData).subscribe({
      next: (response: any) => {
        this.alertService.success(
          'Éxito',
          `Empleados importados correctamente. Creados: ${response.data?.creados || 0}, Actualizados: ${response.data?.actualizados || 0}`
        );
        this.modalRef.hide();
        this.loadEmpleados(); // Recargar la lista de empleados
        this.procesandoImportacion = false;
        this.datosImportacion.archivo = null;
        
        // Mostrar errores si los hay
        if (response.data?.errores && response.data.errores.length > 0) {
          const errores = response.data.errores
            .map((e: any) => `${e.nombre}: ${e.error}`)
            .join('\n');
          this.alertService.error(`Errores en la importación:\n${errores}`);
        }
      },
      error: (error) => {
        this.alertService.error(error);
        this.procesandoImportacion = false;
      },
    });
  }

  public openModalDarAlta(template: TemplateRef<any>, empleado: any) {
    this.empleado = { ...empleado };
    this.modalRef = this.modalService.show(template);
  }

  public darBaja() {
    if (
      !this.empleado.fecha_fin ||
      !this.empleado.fecha_baja ||
      !this.empleado.tipo_baja ||
      !this.empleado.motivo
    ) {
      this.alertService.error('Por favor complete todos los campos requeridos');
      return;
    }
  
    // Validar fecha de notificación
    const fechaNotificacion = new Date(this.empleado.fecha_fin);
    const fechaIngreso = new Date(this.empleado.fecha_ingreso);
    if (fechaNotificacion < fechaIngreso) {
      this.alertService.error(
        'La fecha de notificación no puede ser anterior a la fecha de ingreso'
      );
      return;
    }
  
    // Validar fecha efectiva de baja
    const fechaEfectiva = new Date(this.empleado.fecha_baja);
    if (fechaEfectiva < fechaIngreso) {
      this.alertService.error(
        'La fecha efectiva de baja no puede ser anterior a la fecha de ingreso'
      );
      return;
    }
  
    // Validar que la fecha efectiva no sea anterior a la notificación
    if (fechaEfectiva < fechaNotificacion) {
      this.alertService.error(
        'La fecha efectiva de baja no puede ser anterior a la fecha de notificación'
      );
      return;
    }

    this.saving = true;

    const formData = new FormData();
    formData.append('fecha_baja', this.empleado.fecha_baja);
    formData.append('fecha_fin', this.empleado.fecha_fin);
    formData.append('tipo_baja', this.empleado.tipo_baja);
    formData.append('motivo', this.empleado.motivo);

    if (this.empleado.documento_respaldo instanceof File) {
      formData.append('documento_respaldo', this.empleado.documento_respaldo);
    }

    this.apiService
      .store(`empleados/${this.empleado.id}/dar-baja`, formData)
      .subscribe({
        next: () => {
          this.modalRef.hide();
          this.saving = false;
          this.alertService.success(
            'Exito',
            'Empleado dado de baja correctamente'
          );
          this.loadEmpleados();
        },
        error: (error) => {
          this.alertService.error(error);
          this.saving = false;
        },
      });
  }
  
  public darAlta() {
    if (!this.empleado.fecha_alta) {
      this.alertService.error('Por favor complete todos los campos requeridos');
      return;
    }

    // Validar fecha de alta
    const fechaAlta = new Date(this.empleado.fecha_alta);
    const fechaBaja = new Date(this.empleado.fecha_baja);
    if (fechaAlta <= fechaBaja) {
      this.alertService.error(
        'La fecha de alta debe ser posterior a la fecha de baja'
      );
      return;
    }

    this.saving = true;

    const formData = new FormData();
    formData.append('fecha_alta', this.empleado.fecha_alta);

    if (this.empleado.documento_respaldo instanceof File) {
      formData.append('documento_respaldo', this.empleado.documento_respaldo);
    }

    this.apiService
      .store(`empleados/${this.empleado.id}/dar-alta`, formData)
      .subscribe({
        next: () => {
          this.modalRef.hide();
          this.saving = false;
          this.alertService.success(
            'Éxito',
            'Empleado dado de alta correctamente'
          );
          this.loadEmpleados();
        },
        error: (error) => {
          this.alertService.error(error);
          this.saving = false;
        },
      });
  }
}
