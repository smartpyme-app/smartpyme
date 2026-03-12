import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cuenta',
  templateUrl: './cuenta.component.html'
})
export class CuentaComponent implements OnInit {

    public cuenta: any = {};
    public loading = false;
    public saving = false;

    constructor(
        private apiService: ApiService,
        private alertService: AlertService,
        private route: ActivatedRoute,
        private router: Router
    ) {}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        const idParam = this.route.snapshot.paramMap.get('id');
        const id = idParam && idParam !== 'crear' ? +idParam : 0;

        if (id) {
            this.loading = true;
            this.apiService.read('banco/cuenta/', id).subscribe(
                (cuenta) => {
                    this.cuenta = cuenta;
                    this.loading = false;
                },
                (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            );
        } else {
            this.cuenta = {
                nombre_banco: '',
                tipo: 'Banco',
                numero: '',
                correlativo_cheques: '',
                saldo: 0,
                id_empresa: this.apiService.auth_user().id_empresa
            };
        }
    }

    public onSubmit() {
        this.saving = true;

        this.apiService.store('banco/cuenta', this.cuenta).subscribe(
            (cuenta) => {
                if (!this.cuenta.id) {
                    this.alertService.success('Cuenta creada', 'La cuenta fue creada exitosamente.');
                } else {
                    this.alertService.success('Cuenta guardada', 'La cuenta fue guardada exitosamente.');
                }
                this.router.navigate(['/bancos/cuentas']);
                this.saving = false;
            },
            (error) => {
                this.alertService.error(error);
                this.saving = false;
            }
        );
    }
}
