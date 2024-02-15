import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-tienda-venta-paquetes',
  templateUrl: './tienda-venta-paquetes.component.html'
})
export class TiendaVentaPaquetesComponent implements OnInit {

    @Input() venta: any = {};
    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;

    public paquetes:any = [];
    public clientes:any = [];
    public detalle:any = {};
    public detalles:any = [];
    public filtros:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {

    }

    public openModal(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });
        this.loadAll();
        this.modalRef = this.modalService.show(template, { class: 'modal-xl', backdrop: 'static' });
    }

    public loadAll() {
        this.filtros.id_cliente = '';
        this.filtros.id_usuario = '';
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 5;
        this.filtrarPaquetes();
    }

    public filtrarPaquetes(){
        this.loading = true;
        this.apiService.getAll('paquetes', this.filtros).subscribe(paquetes => { 
            this.paquetes = paquetes;
            console.log(this.paquetes);
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarPaquetes();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.paquetes.path + '?page='+ event.page, this.filtros).subscribe(paquetes => { 
            this.paquetes = paquetes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    selectProducto(paquete:any){
        this.detalle = Object.assign({}, paquete);
        this.detalle.id_paquete    = paquete.id;
        this.detalle.nombre_paquete = paquete.nombre;
        this.detalle.img            = paquete.img;
        this.detalle.precio         = parseFloat(paquete.precio);
        this.detalle.precios        = paquete.precios;
        this.detalle.precios.unshift({
                'precio' : this.detalle.precio
            });
        this.detalle.costo          = parseFloat(paquete.costo);
        paquete.inventarios        = paquete.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
        if(paquete.inventarios.length > 0){
            this.detalle.stock          = parseFloat(this.sumPipe.transform(paquete.inventarios, 'stock'));
        }else{
            this.detalle.stock = null;
        }
        this.detalle.cantidad       = 1;
        this.detalle.descuento      = 0;
        this.detalle.descuento_porcentaje      = 0;
        console.log(this.detalle);
        this.onSubmit();
    }

    onCheckPaquete(paquete:any){
        let radio = document.getElementById('paquete' + paquete.id) as HTMLInputElement;
        if(radio.checked){
            // radio.checked = true
            this.detalle = Object.assign({}, paquete);
            this.detalle.id_paquete    = paquete.id;
            this.detalle.nombre_producto = 'Servicio de importación de paquetería';
            this.detalle.img            = 'productos/default.jpg';
            this.detalle.precio         = parseFloat(paquete.precio);
            this.detalle.cantidad       = parseFloat(paquete.peso);
            this.detalle.descuento      = 0;
            this.detalle.descuento_porcentaje      = 0;
            this.detalles.unshift(this.detalle);
        }else{
            // radio.checked = false;
            const indexAEliminar = this.detalles.findIndex((item:any) => item.id_paquete === paquete.id);
            if (indexAEliminar !== -1) {
              this.detalles.splice(indexAEliminar, 1);
            }
            console.log(indexAEliminar);
        }

        console.log(this.detalles);
    }

    onSubmit(){
        this.paquetes = [];
        this.productoSelect.emit(this.detalle);
        if(this.modalRef){
            this.modalRef.hide();
        }
    }

    agregarDetalles(){
        for (let i = 0; i < this.detalles.length; i++) { 
            this.productoSelect.emit(this.detalles[i]);
        }

        if(this.modalRef){
            this.modalRef.hide();
        }
    }


}
