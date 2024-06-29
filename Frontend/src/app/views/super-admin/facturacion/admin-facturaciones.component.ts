import { Component, OnInit, Input, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-admin-facturacion',
  templateUrl: './admin-facturaciones.component.html'
})

export class AdminFacturacionesComponent implements OnInit{

  public transacciones:any= [];
  public usuario:any = {};
  public sucursales:any = [];
  public filtros:any = {};
  public loading:boolean = false;

  modalRef!: BsModalRef;

  constructor(public apiService: ApiService, private alertService: AlertService,private modalService: BsModalService){};

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.loadAll();

    this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
        this.sucursales = sucursales;
    }, error => {this.alertService.error(error); });
}

public loadAll() {
  this.filtros.id_sucursal = '';
  this.filtros.id_cliente = '';
  this.filtros.id_usuario = '';
  this.filtros.id_vendedor = '';
  this.filtros.id_canal = '';
  this.filtros.id_documento = '';
  this.filtros.forma_pago = '';
  this.filtros.estado = '';
  this.filtros.buscador = '';
  this.filtros.orden = 'fecha';
  this.filtros.direccion = 'desc';
  this.filtros.paginate = 10;

  this.filtrarTransacciones();
}

public filtrarTransacciones(){

  this.loading = true;
  this.apiService.getAll('transacciones', this.filtros).subscribe(transacciones => { 
    this.transacciones = transacciones;
    this.loading = false;
    if(this.modalRef){
        this.modalRef.hide();
    }
}, error => {this.alertService.error(error); this.loading = false;});

}


}

