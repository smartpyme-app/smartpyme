import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-partidas',
  templateUrl: './partidas.component.html',
  styleUrls: ['./partidas.component.scss']
})
export class PartidasComponent implements OnInit {
  public partidas: any = [];
  public partida: any = {};
  public loading: boolean = false;
  public saving: boolean = false;
  public filtros: any = {};
  public reporte = {
    month: new Date().getMonth() + 1,
    year: new Date().getFullYear(),
    concepto: '',
    cuenta: '',
    tipo_descarga: 'pdf',
    tipo_cuenta: 'all',
  };
  public catalogo: any = [];
  public months: Array<{ value: number; label: string }> = [];
  public years: number[] = [];
  public selectedMonth: number = new Date().getMonth() + 1;
  public selectedYear: number = new Date().getFullYear();

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.generateMonths();
    this.generateYears();
    this.loadAll();
  }

  generateMonths() {
    this.months = [
      { value: 1, label: 'Enero' },
      { value: 2, label: 'Febrero' },
      { value: 3, label: 'Marzo' },
      { value: 4, label: 'Abril' },
      { value: 5, label: 'Mayo' },
      { value: 6, label: 'Junio' },
      { value: 7, label: 'Julio' },
      { value: 8, label: 'Agosto' },
      { value: 9, label: 'Septiembre' },
      { value: 10, label: 'Octubre' },
      { value: 11, label: 'Noviembre' },
      { value: 12, label: 'Diciembre' }
    ];
  }

  generateYears() {
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 5; i <= currentYear + 2; i++) {
      this.years.push(i);
    }
  }

  public setOrden(columna: string) {
    if (this.filtros.columna == columna) {
      this.filtros.orden = this.filtros.orden == 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = 'asc';
    }
    this.filtros.columna = columna;
    this.filtrarPartidas();
  }

  public loadAll() {
    this.loading = true;
    this.apiService.getAll('partidas').subscribe(
      (partidas) => {
        this.partidas = partidas;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public filtrarPartidas() {
    this.loading = true;
    this.apiService.getAll('partidas', this.filtros).subscribe(
      (partidas) => {
        this.partidas = partidas;
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

  public openModal(template: TemplateRef<any>, partida: any) {
    this.partida = partida;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public openFilter(template: TemplateRef<any>) {
    this.modalRef = this.modalService.show(template, {
      class: 'modal-md',
      backdrop: 'static',
    });
  }

  public setEstado(partida: any, estado: any) {
    this.partida = partida;
    this.partida.estado = estado;
    this.onSubmit();
  }

  public setEstadoChange(partida: any) {
    this.apiService.store('partida', partida).subscribe(
      (producto) => {
        this.alertService.success(
          'Partida actualizada',
          'El estado de la partida fue actualizado.'
        );
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService
      .paginate(this.partidas.path + '?page=' + event.page, this.filtros)
      .subscribe(
        (partidas) => {
          this.partidas = partidas;
          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

  public delete(partida: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminarlo',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.delete('partida/', partida.id).subscribe(
          (data) => {
            for (let i = 0; i < this.partidas.data.length; i++) {
              if (this.partidas.data[i].id == data.id)
                this.partidas.data.splice(i, 1);
            }
          },
          (error) => {
            this.alertService.error(error);
          }
        );
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });
  }

  public onSubmit() {
    this.saving = true;
    this.apiService.store('partida', this.partida).subscribe(
      (partida) => {
        if (!this.partida.id) {
          this.loadAll();
          this.alertService.success(
            'Partida creada',
            'El partida fue añadida exitosamente.'
          );
        } else {
          this.alertService.success(
            'Partida guardada',
            'El partida fue guardada exitosamente.'
          );
        }
        this.saving = false;
        if (this.modalRef) {
          this.modalRef.hide();
        }
        this.alertService.modal = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  public imprimirDiarioAux() {
    if (
      this.reporte.month &&
      this.reporte.year &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/libro/diario/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.tipo_cuenta +
          '/' +
          this.reporte.tipo_descarga +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirMayor() {
    if (this.reporte.month && this.reporte.year && this.reporte.concepto) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/libro/diario/mayor/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.tipo_cuenta +
          '/' +
          this.reporte.concepto +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirDiarioMayor() {
    if (
      this.reporte.month &&
      this.reporte.year &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/libro/diario/mayor/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.tipo_cuenta +
          '/' +
          this.reporte.tipo_descarga +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      console.error('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirMovCuenta() {
    if (this.reporte.month && this.reporte.year && this.reporte.cuenta) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/movimiento/cuenta/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.cuenta +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirBalanceComprobacion() {
    if (
      this.reporte.month &&
      this.reporte.year &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/balance/comprobacion/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.tipo_cuenta +
          '/' +
          this.reporte.tipo_descarga +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public abrirPartida(partida: any) {
    this.apiService.store('partidas/abrir', { id: partida.id }).subscribe({
      next: (response) => {
        this.alertService.success('Partida abierta', 'La partida ha sido reabierta exitosamente.');
        this.filtrarPartidas();
      },
      error: (error) => {
        this.alertService.error(error.error.error || 'Error al abrir la partida');
      }
    });
  }

  public imprimirPartida(partida: any) {
    window.open(
      this.apiService.baseUrl + '/api/partidas/descargar/' + partida.id + '?token=' + this.apiService.auth_token(),
      '_blank'
    );
  }
}
