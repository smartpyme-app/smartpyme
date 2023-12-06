import { Component, OnInit, TemplateRef } from '@angular/core';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cuentas-cobrar',
  templateUrl: './cuentas-cobrar.component.html'
})

export class CuentasCobrarComponent implements OnInit {

    public cobros:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    constructor(private apiService: ApiService, private alertService: AlertService){ }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('cuentas-cobrar').subscribe(cobros => { 
            this.cobros = cobros;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.apiService.read('cuentas-cobrar/buscar/', this.buscador).subscribe(cobros => { 
                this.cobros = cobros;
            }, error => {this.alertService.error(error); });
        }
    }

    public setEstado(venta:any, estado:string){
        venta.estado = estado;
        this.apiService.store('venta', venta).subscribe(venta => { 
            this.alertService.success('Venta actualizada', 'La venta fue actualizada exitosamente.');
        }, error => {this.alertService.error(error); });
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.cobros.path + '?page='+ event.page).subscribe(cobros => { 
            this.cobros = cobros;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
