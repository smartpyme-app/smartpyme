// empleados.component.ts
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
    this.apiService.getAll('departamentos/list').subscribe(
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

  public getEstadoClass(estado: number): string {
    if (estado === PlanillaConstants.ESTADOS_EMPLEADO.ACTIVO) return 'bg-success';
    if (estado === PlanillaConstants.ESTADOS_EMPLEADO.INACTIVO) return 'bg-danger';
    if (estado === PlanillaConstants.ESTADOS_EMPLEADO.VACACIONES) return 'bg-info';
    if (estado === PlanillaConstants.ESTADOS_EMPLEADO.INCAPACIDAD) return 'bg-warning';
    if (estado === PlanillaConstants.ESTADOS_EMPLEADO.SUSPENDIDO) return 'bg-secondary';
    return '';
  }

  public getNombreEstadoEmpleado(estado: number): string {
    return PlanillaConstants.getNombreEstadoEmpleado(estado);
  }

  public openModalDarBaja(template: TemplateRef<any>, empleado: any) {
    this.empleado = { ...empleado };
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

  public openModalDarAlta(template: TemplateRef<any>, empleado: any) {
    this.empleado = { ...empleado };
    this.modalRef = this.modalService.show(template);
  }

  public darBaja() {
    if (
      !this.empleado.fecha_baja ||
      !this.empleado.tipo_baja ||
      !this.empleado.motivo
    ) {
      this.alertService.error('Por favor complete todos los campos requeridos');
      return;
    }

    // Validar fecha de baja
    const fechaBaja = new Date(this.empleado.fecha_baja);
    const fechaIngreso = new Date(this.empleado.fecha_ingreso);
    if (fechaBaja < fechaIngreso) {
      this.alertService.error(
        'La fecha de baja no puede ser anterior a la fecha de ingreso'
      );
      return;
    }

    this.saving = true;

    const formData = new FormData();
    formData.append('fecha_baja', this.empleado.fecha_baja);
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
