import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { formatDate } from '@angular/common';
import Swal from 'sweetalert2';
import { AppConstants } from '../../../../app/constants/app.constants';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';

interface Plan {
  id: number;
  nombre: string;
  monto: number;
}

interface OrdenPago {
  id_orden: string;
  fecha_transaccion: string;
  monto: string;
  metodo_pago?: string;
  estado: string;
  codigo_autorizacion?: string;
  comprobante_url?: string;
}

@Component({
    selector: 'app-admin-suscripciones',
    templateUrl: './admin-suscripciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})
export class AdminSuscripcionesComponent implements OnInit {
  public suscripciones: any = [];
  public suscripcion: any = {};
  public usuario: any = {};
  public empresa: any = {};
  public users: any[] = [];
  public filtros: any = {};
  public loading: boolean = false;
  public saving: boolean = false;
  public nuevaSuscripcion: any = {};
  public tabActivo: 'n1co' | 'transferencia' = 'n1co';
  public editando: boolean = false;
  public downloading:boolean = false;

  public historialPagos: OrdenPago[] = [];
  public loadingHistorial: boolean = false;

  public planes: Plan[] = [
    {
      id: AppConstants.PLANID.EMPRENDEDOR,
      nombre: AppConstants.PLANES.EMPRENDEDOR.NOMBRE,
      monto: AppConstants.PLANES.EMPRENDEDOR.PRECIO,
    },
    {
      id: AppConstants.PLANID.ESTANDAR,
      nombre: AppConstants.PLANES.ESTANDAR.NOMBRE,
      monto: AppConstants.PLANES.ESTANDAR.PRECIO,
    },
    {
      id: AppConstants.PLANID.AVANZADO,
      nombre: AppConstants.PLANES.AVANZADO.NOMBRE,
      monto: AppConstants.PLANES.AVANZADO.PRECIO,
    },
    {
      id: AppConstants.PLANID.PRO,
      nombre: AppConstants.PLANES.PRO.NOMBRE,
      monto: AppConstants.PLANES.PRO.PRECIO,
    },
  ];

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.loadAll();
  }

  public loadAll() {
    this.filtros = {
      estado: '',
      buscador: '',
      orden: 'fecha_ultimo_pago',
      direccion: 'desc',
      paginate: 10,
    };

    this.filtrarSuscripciones();
  }

  public filtrarSuscripciones() {
    this.loading = true;

    this.apiService.getAll('suscripciones', this.filtros).subscribe(
      (suscripciones) => {
        this.suscripciones = suscripciones;
        this.loading = false;
        if (this.modalRef) {
          this.modalRef.hide();
        }
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService
      .paginate(this.suscripciones.path + '?page=' + event.page, this.filtros)
      .subscribe(
        (suscripciones) => {
          this.suscripciones = suscripciones;
          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

  public openDetalles(template: TemplateRef<any>, suscripcion: any) {
    this.suscripcion = suscripcion;
    this.modalRef = this.modalService.show(template);
  }

  public openEditar(template: TemplateRef<any>, suscripcion: any) {
    this.getUsersForSelect(suscripcion.empresa_id)
      .then(() => {
        this.editando = true;
        this.suscripcion = {
          ...suscripcion,
          fecha_proximo_pago: this.formatearFecha(
            suscripcion.fecha_proximo_pago
          ),
          fin_periodo_prueba: this.formatearFecha(
            suscripcion.fin_periodo_prueba
          ),
        };
        this.modalRef = this.modalService.show(template);
      })
      .catch((error) => {
        this.alertService.error('No se pudo obtener usuarios');
      });
  }

  private formatearFecha(fecha: string): string {
    if (!fecha) return '';
    return formatDate(new Date(fecha), 'yyyy-MM-dd', 'en');
  }

  public openCancelar(template: TemplateRef<any>, suscripcion: any) {
    this.suscripcion = suscripcion;
    this.modalRef = this.modalService.show(template);
  }

  public cancelarSuscripcion() {
    if (!this.suscripcion?.id || !this.suscripcion?.motivo_cancelacion) {
      return;
    }

    this.saving = true;
    this.apiService
      .store('suscripcion/cancel', {
        id: this.suscripcion.id,
        motivo_cancelacion: this.suscripcion.motivo_cancelacion,
      })
      .subscribe(
        (response) => {
          this.saving = false;
          this.alertService.success(
            'Éxito',
            'Suscripción cancelada exitosamente'
          );
          this.modalRef.hide();
          this.filtrarSuscripciones();
        },
        (error) => {
          this.alertService.error(error);
          this.saving = false;
        }
      );
  }

  public onSubmitEditSuscription() {
    if (!this.suscripcion.id) {
      this.alertService.error('ID de suscripción no válido');
      return;
    }

    this.saving = true;

    const datosSuscripcion = {
      ...this.suscripcion,
      usuario_id: this.suscripcion.usuario_id,
      fecha_proximo_pago: new Date(this.suscripcion.fecha_proximo_pago),
      fin_periodo_prueba: new Date(this.suscripcion.fin_periodo_prueba),
    };

    this.apiService.store('suscripcion/edit', datosSuscripcion).subscribe(
      (response) => {
        this.alertService.success(
          'Éxito',
          'Suscripción actualizada correctamente'
        );
        this.modalRef.hide();
        this.filtrarSuscripciones();
        this.saving = false;
      },
      (error) => {
        this.saving = false;
        this.alertService.error(
          error.message || 'Error actualizando suscripción'
        );
      }
    );
  }

  private getUsersForSelect(id_empresa: number) {
    return new Promise<any[]>((resolve, reject) => {
      const params = {
        id_empresa: id_empresa,
      };

      this.apiService
        .store('suscripcion/getUsersSelect', { params })
        .subscribe({
          next: (response: any) => {
            this.users = response;
            resolve(response);
          },
          error: (error) => {
            this.alertService.error('Error al cargar usuarios: ' + error);
            reject(error);
          },
        });
    });
  }

  public openCrearSuscripcion(template: TemplateRef<any>, empresa: any) {
    if (!empresa) {
      this.alertService.error('No se proporcionaron datos de la empresa');
      return;
    }

    this.getUsersForSelect(empresa.id)
      .then(() => {
        (this.editando = false),
          (this.nuevaSuscripcion = {
            empresa_id: empresa.id,
            usuario_id: '',
            plan_id: '',
            nombre_factura: empresa.nombre,
            direccion_factura: empresa.direccion,
            nit: empresa.nit,
            tipo_plan: '',
            estado: 'En prueba',
            monto: 0,
            fecha_proximo_pago: this.formatearFecha(new Date().toISOString()),
            fin_periodo_prueba: this.formatearFecha(
              new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString()
            ),
            requiere_factura: Boolean(empresa.nit),
          });

        this.modalRef = this.modalService.show(template);
      })
      .catch((error) => {
        this.alertService.error('No se pudo obtener usuarios');
      });
  }

  public selectPlan(planId: number) {
    const planSeleccionado = this.planes.find(
      (plan) => plan.id === Number(planId)
    );
    if (planSeleccionado) {
      this.nuevaSuscripcion.plan_id = planSeleccionado.id;
      this.nuevaSuscripcion.monto = planSeleccionado.monto;
    }
  }

  public selectPlanEdit(planId: number) {
    const planSeleccionado = this.planes.find(
      (plan) => plan.id === Number(planId)
    );
    if (planSeleccionado) {
      if (!this.suscripcion.plan) {
        this.suscripcion.plan = {};
      }
      this.suscripcion.plan.id = planSeleccionado.id;
      this.suscripcion.monto = planSeleccionado.monto;
    }
  }

  public onSubmitCreateSuscription() {
    if (!this.nuevaSuscripcion.empresa_id) {
      this.alertService.error('ID de empresa no válido');
      return;
    }

    this.saving = true;

    const datosSuscripcion = {
      ...this.nuevaSuscripcion,
      fecha_proximo_pago: new Date(this.nuevaSuscripcion.fecha_proximo_pago),
      fin_periodo_prueba: new Date(this.nuevaSuscripcion.fin_periodo_prueba),
      nit: this.nuevaSuscripcion.nit || null,
      nombre_factura: this.nuevaSuscripcion.nombre_factura || null,
      direccion_factura: this.nuevaSuscripcion.direccion_factura || null,
      requiere_factura: this.nuevaSuscripcion.nit ? 1 : 0,
      giro: this.nuevaSuscripcion.giro || null,
      telefono: this.nuevaSuscripcion.telefono || null,
      correo: this.nuevaSuscripcion.correo || null,
    };

    this.apiService.store('suscripcion/create', datosSuscripcion).subscribe({
      next: (response) => {
        this.saving = false;
        this.alertService.success('Éxito', 'Suscripción creada correctamente');
        this.modalRef.hide();
        this.filtrarSuscripciones();
      },
      error: (error) => {
        this.saving = false;
        this.alertService.error(error.message || 'Error creando suscripción');
      },
    });
  }

  public openFilter(template: TemplateRef<any>) {
    this.modalRef = this.modalService.show(template);
  }

  public suspenderSistema(empresa: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: '¿Desea suspender el acceso al sistema ?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, suspender',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.store('suscripcion/suspender', { empresa }).subscribe({
          next: () => {
            Swal.fire(
              '¡Suspendido!',
              'El acceso al sistema ha sido suspendido',
              'success'
            );
            this.filtrarSuscripciones();
          },
          error: (error) => {
            Swal.fire('No se pudo suspender el sistema.', 'error');
            this.filtrarSuscripciones();
          },
        });
      }
    });
  }

  public activarSistema(empresa: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: '¿Desea activar el acceso al sistema?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, activar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.store('suscripcion/activar', { empresa }).subscribe({
          next: () => {
            Swal.fire(
              '¡Activado!',
              'El acceso al sistema ha sido activado',
              'success'
            );
            this.filtrarSuscripciones();
          },
          error: (error) => {
            Swal.fire('No se pudo activar el sistema.', 'error');
            this.filtrarSuscripciones();
          },
        });
      }
    });
  }

  public openHistorialPagos(
    template: TemplateRef<any>,
    suscripcion: any,
    empresa: any
  ) {
    this.loadingHistorial = true;
    this.suscripcion = suscripcion;
    this.suscripcion.empresa = empresa;
    this.tabActivo = 'n1co'; // Establecer tab por defecto

    this.apiService.getAll(`suscripciones/${suscripcion.id}/pagos`).subscribe({
      next: (response) => {
        console.log('Historial de pagos:', response); // Para debug
        this.historialPagos = response;
        this.loadingHistorial = false;
      },
      error: (error) => {
        console.error('Error cargando historial:', error); // Para debug
        this.alertService.error('Error al cargar el historial de pagos');
        this.loadingHistorial = false;
      },
    });

    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  public getPagosFiltrados(tipo: 'n1co' | 'transferencia'): OrdenPago[] {
    if (!this.historialPagos || !Array.isArray(this.historialPagos)) {
      return [];
    }

    return this.historialPagos.filter((pago) => {
      if (tipo === 'n1co') {
        return (
          pago.metodo_pago === 'n1co' ||
          pago.metodo_pago === 'tarjeta' ||
          !pago.metodo_pago
        );
      } else {
        return pago.metodo_pago === 'transferencia';
      }
    });
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
  
    this.filtrarSuscripciones();
  }

  public descargar(){
    this.downloading = true;
    this.apiService.export('suscripciones/exportar', this.filtros).subscribe((data:Blob) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'suscripciones.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      }, (error) => { this.alertService.error(error); this.downloading = false; }
    );
  }
}
