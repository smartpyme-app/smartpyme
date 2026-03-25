import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-cuentas',
  templateUrl: './cuentas.component.html'
})
export class CuentasComponent implements OnInit {

    public cuentas: any = [];
    public loading = false;
    public filtros: any = {};

    constructor(
        public apiService: ApiService,
        private alertService: AlertService
    ) {}

    ngOnInit() {
        this.loadAll();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = 'asc';
        }
        this.filtrarCuentas();
    }

    public loadAll() {
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarCuentas();
    }

    public filtrarCuentas() {
        this.loading = true;
        this.apiService.getAll('bancos/cuentas', this.filtros).subscribe(
            (cuentas) => {
                this.cuentas = cuentas;
                this.loading = false;
            },
            (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
        );
    }

    public setPagination(event: any) {
        this.loading = true;
        this.apiService.paginate(this.cuentas.path + '?page=' + event.page, this.filtros).subscribe(
            (cuentas) => {
                this.cuentas = cuentas;
                this.loading = false;
            },
            (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
        );
    }

    public delete(cuenta: any) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¡No podrás revertir esto!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminarlo',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.apiService.delete('banco/cuenta/', cuenta.id).subscribe(
                    (data) => {
                        for (let i = 0; i < this.cuentas.data.length; i++) {
                            if (this.cuentas.data[i].id === data.id) {
                                this.cuentas.data.splice(i, 1);
                                this.cuentas.total = this.cuentas.total - 1;
                                break;
                            }
                        }
                        this.alertService.success('Cuenta eliminada', 'La cuenta fue eliminada exitosamente.');
                    },
                    (error) => this.alertService.error(error)
                );
            }
        });
    }
}
