import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';


@Component({
  selector: 'app-caja-ventas',
  templateUrl: './caja-ventas.component.html'
})

export class CajaVentasComponent implements OnInit {

    public ventas:any = [];
    public loading:boolean = false;
    public buscador:any = '';

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('corte/ventas/' + JSON.parse(sessionStorage.getItem('worder_corte')!).id).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    reemprimir(venta:any){
        if(venta.tipo_documento == 'Factura' || venta.tipo_documento == 'Credito Fiscal' || venta.tipo_documento == 'Ticket'){
            window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }
    }


}
