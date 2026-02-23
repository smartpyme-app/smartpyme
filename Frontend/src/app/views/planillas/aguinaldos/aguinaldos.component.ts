import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaConstants } from '../../../constants/planilla.constants';
import { Router } from '@angular/router';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-aguinaldos',
  templateUrl: './aguinaldos.component.html',
})
export class AguinaldosComponent implements OnInit {
  public aguinaldos: any = [];
  public loading: boolean = false;
  public saving: boolean = false;
  public aguinaldoNuevo: any = {
    anio: new Date().getFullYear()
  };

  public filtros: any = {
    anio: '',
    estado: '',
    buscador: '',
    paginate: 10,
    orden: 'created_at',
    direccion: 'desc',
  };

  public usuario: any = {};
  public anios: number[] = [];
  modalRef!: BsModalRef;

  @ViewChild('mNuevoAguinaldo') mNuevoAguinaldo!: TemplateRef<any>;

  // Estados de aguinaldo
  public ESTADOS_AGUINALDO = {
    BORRADOR: PlanillaConstants.AGUINALDO?.ESTADOS?.BORRADOR || 1,
    PAGADO: PlanillaConstants.AGUINALDO?.ESTADOS?.PAGADO || 2,
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private router: Router
  ) {
    this.generarAnios();
  }

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.loadAguinaldos();
  }

  private generarAnios() {
    const currentYear = new Date().getFullYear();
    this.anios = [];
    // Generamos los últimos 5 años y el próximo año
    for (let year = currentYear + 1; year >= currentYear - 5; year--) {
      this.anios.push(year);
    }
  }

  public loadAguinaldos() {
    this.loading = true;
    this.apiService.getAll('aguinaldos', this.filtros).subscribe({
      next: (aguinaldos: any) => {
        this.aguinaldos = aguinaldos;
        this.loading = false;
      },
      error: (error: any) => {
        this.alertService.error(error);
        this.loading = false;
      },
    });
  }

  public filtrarAguinaldos() {
    this.loadAguinaldos();
    if (this.modalRef) {
      this.modalRef.hide();
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
    this.filtrarAguinaldos();
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService
      .paginate(this.aguinaldos.path + '?page=' + event.page, this.filtros)
      .subscribe({
        next: (aguinaldos: any) => {
          this.aguinaldos = aguinaldos;
          this.loading = false;
        },
        error: (error: any) => {
          this.alertService.error(error);
          this.loading = false;
        },
      });
  }

  public openNuevoAguinaldo(template: TemplateRef<any>) {
    const currentYear = new Date().getFullYear();
    this.aguinaldoNuevo = {
      anio: currentYear,
      fecha_calculo: currentYear + '-12-12' // Por defecto 12 de diciembre
    };
    this.modalRef = this.modalService.show(template, {
      class: 'modal-md',
      backdrop: 'static',
    });
  }

  public crearAguinaldo() {
    if (!this.aguinaldoNuevo.anio || this.aguinaldoNuevo.anio < 2020) {
      this.alertService.error('Debe seleccionar un año válido');
      return;
    }

    this.saving = true;
    this.apiService.store('aguinaldos', this.aguinaldoNuevo).subscribe({
      next: (response: any) => {
        this.alertService.success('Éxito', 'Aguinaldo creado exitosamente');
        this.modalRef.hide();
        this.loadAguinaldos();
        this.saving = false;
        // Redirigir al detalle del aguinaldo creado
        if (response.aguinaldo?.id) {
          this.router.navigate(['/planilla/aguinaldo/detalle', response.aguinaldo.id]);
        }
      },
      error: (error: any) => {
        this.alertService.error(error);
        this.saving = false;
      },
    });
  }

  public verDetalle(id: number) {
    this.router.navigate(['/planilla/aguinaldo/detalle', id]);
  }

  public eliminarAguinaldo(id: number) {
    Swal.fire({
      title: '¿Está seguro?',
      text: 'Esta acción no se puede deshacer',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.loading = true;
        this.apiService.delete('aguinaldos/', id).subscribe({
          next: () => {
            this.alertService.success('Éxito', 'Aguinaldo eliminado exitosamente');
            this.loadAguinaldos();
          },
          error: (error: any) => {
            this.alertService.error(error);
            this.loading = false;
          },
        });
      }
    });
  }

  public getEstadoNombre(estado: number): string {
    if (estado === this.ESTADOS_AGUINALDO.BORRADOR) {
      return 'Borrador';
    } else if (estado === this.ESTADOS_AGUINALDO.PAGADO) {
      return 'Pagado';
    }
    return 'Desconocido';
  }

  public getEstadoBadgeClass(estado: number): string {
    if (estado === this.ESTADOS_AGUINALDO.BORRADOR) {
      return 'bg-warning';
    } else if (estado === this.ESTADOS_AGUINALDO.PAGADO) {
      return 'bg-success';
    }
    return 'bg-secondary';
  }

}
