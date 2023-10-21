import { Component, OnInit, TemplateRef } from '@angular/core';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-cuentas-pagar',
  templateUrl: './cuentas-pagar.component.html'
})

export class CuentasPagarComponent implements OnInit {

	public pagos:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    constructor(private apiService: ApiService, private alertService: AlertService){ }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('cuentas-pagar').subscribe(pagos => { 
            this.pagos = pagos;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.apiService.read('cuentas-pagar/buscar/', this.buscador).subscribe(pagos => { 
                this.pagos = pagos;
            }, error => {this.alertService.error(error); });
        }
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.pagos.path + '?page='+ event.page).subscribe(pagos => { 
            this.pagos = pagos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
