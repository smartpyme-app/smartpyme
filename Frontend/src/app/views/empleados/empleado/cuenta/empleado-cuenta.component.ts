import { Component, OnInit, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-empleado-cuenta',
  templateUrl: './empleado-cuenta.component.html'
})
export class EmpleadoCuentaComponent implements OnInit {

    @Input() empleado: any = {};
    public cajas: any = [];
    public sucursales: any = [];
    public departamentos: any = [];
    public loading = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router
    ) { }

    ngOnInit() {
        
        if(!this.empleado.cuenta){
            this.empleado.cuenta = {};
            this.empleado.cuenta.name = this.empleado.nombre;
            this.empleado.cuenta.tipo = 'Vendedor';
            this.empleado.cuenta.sucursal_id = this.apiService.auth_user().sucursal_id;
            this.empleado.cuenta.activo = true;
            this.empleado.cuenta.empleado_id = this.empleado.id;
        }

        this.apiService.getAll('cajas').subscribe(cajas => { 
            this.cajas = cajas;
            this.loading = false;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('sucursales').subscribe(sucursales => { 
            this.sucursales = sucursales;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public onSubmit() {
        this.loading = true;
        this.apiService.store('usuario', this.empleado.cuenta).subscribe(cuanta => {
            this.empleado.cuenta = cuanta;
            this.loading = false;
            this.alertService.success("Usuario guardado");
        },error => {this.alertService.error(error); this.loading = false; });

    }

}
