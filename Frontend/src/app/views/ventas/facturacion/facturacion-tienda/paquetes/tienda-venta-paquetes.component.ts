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
    public servicio:any = {};
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
        this.apiService.getAll('paquetes/pendientes/clientes').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });
        this.loadAll();
        this.modalRef = this.modalService.show(template, { class: 'modal-xl', backdrop: 'static' });
        
        this.apiService.getAll('productos', {buscador: 'Servicio de importación de paquetería'}).subscribe(productos => { 
            if(productos.data[0]){
                this.servicio = productos.data[0];
            }else{
                alert('No se encontro un servicio para facturar paquetes, debe ingresar a servicios y agregarlo con el nombre: "Servicio de importación de paquetería"');
                this.modalRef.hide();
            }
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public loadAll() {
        this.filtros.id_cliente = this.venta.id_cliente;
        this.filtros.id_usuario = '';
        this.filtros.tipo = '';
        this.filtros.estado = 'En bodega';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 5;
        this.filtrarPaquetes();
    }

    public filtrarPaquetes(){
        this.loading = true;
        this.venta.id_cliente = this.filtros.id_cliente;
        this.apiService.getAll('paquetes', this.filtros).subscribe(paquetes => { 
            this.paquetes = paquetes;
            
            this.detalles = [];
            let radio = document.getElementById('marcarPaquetes') as HTMLInputElement;
            radio.checked = false;

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
        this.detalle.descripcion   = 'Número: ' + paquete.wr + ' Guia: ' + paquete.num_guia;
        this.detalle.img            = paquete.img;
        this.detalle.precio         = parseFloat(paquete.precio);
        this.detalle.precios        = paquete.precios;
        this.detalle.precios.unshift({
                'precio' : this.detalle.precio
            });
        this.detalle.costo          = parseFloat(paquete.costo);
        paquete.inventarios        = paquete.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
        if(paquete.inventarios.length > 0 && this.detalle.tipo != 'Servicio'){
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
        console.log(paquete);
        let radio = document.getElementById('paquete' + paquete.id) as HTMLInputElement;
        if(radio.checked){
            if(!this.venta.id_cliente && paquete.id_cliente){
                this.venta.id_cliente = paquete.id_cliente;
            }
            this.detalle = Object.assign({}, this.servicio);
            this.detalle.id_producto    = this.servicio.id;
            this.detalle.descripcion = 'Número: ' + paquete.wr + ' Guia: ' + paquete.num_guia;
            this.detalle.img            = this.servicio.img;
            // this.detalle.precio         = parseFloat(this.servicio.precio);
            this.detalle.id_paquete    = paquete.id;
            this.detalle.precio        = ((parseFloat(paquete.precio) + parseFloat(paquete.otros)) / 1.13).toFixed(4);
            this.detalle.total         = (parseFloat(paquete.total) / 1.13).toFixed(4);
            // this.detalle.total         = parseFloat(paquete.total);
            this.detalle.cuenta_a_terceros        = parseFloat(paquete.cuenta_a_terceros);

            // this.detalle.precios        = this.servicio.precios;
            // this.detalle.precios.unshift({
            //         'precio' : this.detalle.precio
            //     });
            this.detalle.costo          = parseFloat(this.servicio.costo);
            this.servicio.inventarios        = this.servicio.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
            if(this.servicio.inventarios.length > 0 && this.servicio.tipo != 'Servicio'){
                this.detalle.stock          = parseFloat(this.sumPipe.transform(this.servicio.inventarios, 'stock'));
            }else{
                this.detalle.stock = null;
            }
            // this.detalle.cantidad       = 1;
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

    onCheckAllPaquete(){
        let marcarPaquetes = document.getElementById('marcarPaquetes') as HTMLInputElement;
        this.paquetes.data.forEach((paquete:any) => {
            let radio = document.getElementById('paquete' + paquete.id) as HTMLInputElement;
            radio.checked = marcarPaquetes.checked;
            this.onCheckPaquete(paquete);
        });
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
