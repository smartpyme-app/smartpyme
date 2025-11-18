import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

import * as moment from 'moment';

@Component({
    selector: 'app-tienda-venta-citas',
    templateUrl: './tienda-venta-citas.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule, NgSelectModule],
    
})
export class TiendaVentaCitasComponent extends BasePaginatedComponent implements OnInit {

    @Input() venta: any = {};
    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;

    public citas: PaginatedResponse<any> = {} as PaginatedResponse;
    public clientes:any = [];
    public detalle:any = {};
    public detalles:any = [];
    public override filtros:any = {};
    public buscador:any = '';
    public saving:boolean = false;

    constructor( 
        apiService: ApiService, alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) {
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.citas;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.citas = data;
    }

    ngOnInit() {

    }

    public openModal(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list').pipe(this.untilDestroyed()).subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });
        this.citas = {} as PaginatedResponse;
        this.loadAll();
        this.modalRef = this.modalService.show(template, { class: 'modal-xl', backdrop: 'static' });        
    }

    public loadAll() {
        this.filtros.id_cliente = this.venta.id_cliente;
        this.filtros.id_usuario = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'inicio';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 5;
        
        this.filtros.time = 'week';
        this.filtros.inicio = moment().startOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtros.fin = moment().endOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtrarCitas();

    }

    public setTime($time:any){
        this.filtros.time = $time;
        this.filtros.inicio = moment().startOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtros.fin = moment().endOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtrarCitas();
    }

    public filtrarCitas(){
        this.loading = true;
        this.venta.id_cliente = this.filtros.id_cliente;
        this.apiService.getAll('eventos', this.filtros).pipe(this.untilDestroyed()).subscribe(citas => { 
            this.citas = citas;
            
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

        this.filtrarCitas();
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    onCheckPaquete(cita:any){
        console.log(cita);
        let radio = document.getElementById('cita' + cita.id) as HTMLInputElement;
        if(radio.checked){
            
            cita.productos.forEach((detalleProducto: any) => {
                this.saving = true;
                this.apiService.read('producto/', detalleProducto.id_producto).pipe(this.untilDestroyed()).subscribe(producto => {
                    let detalle:any = {};
                    detalle.id_cita    = cita.id;
                    detalle.id_producto    = producto.id;
                    detalle.descripcion = producto.nombre;
                    detalle.img            = producto.img;
                    detalle.precio         = parseFloat(producto.precio);
                    detalle.costo          = parseFloat(producto.costo);
                    if(producto.tipo != 'Servicio' && producto.inventarios.length > 0){
                        producto.inventarios   = producto.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
                        detalle.stock          = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
                    }else{
                        detalle.stock = null;
                    }
                    detalle.cantidad       = detalleProducto.cantidad;
                    detalle.descuento      = 0;
                    detalle.descuento_porcentaje      = 0;
                    detalle.total_costo = detalle.costo;
                    detalle.total      = detalle.precio;

                    if(!detalle.exenta){
                        detalle.exenta = 0;
                    }
                    if(!detalle.no_sujeta){
                        detalle.no_sujeta = 0;
                    }
                    if(!detalle.cuenta_a_terceros){
                        detalle.cuenta_a_terceros = 0;
                    }

                    detalle.total = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(4);

                    this.detalles.unshift(detalle);
                    this.saving = false;
                }, error => {this.alertService.error(error); this.saving = false;});
            });

        }else{
            
            this.detalles = this.detalles.filter((item:any) => item.id_cita !== cita.id);

        }

        console.log(this.detalles);
    }

    onCheckAllPaquete(){
        let marcarPaquetes = document.getElementById('marcarPaquetes') as HTMLInputElement;
        this.citas.data.forEach((cita:any) => {
            let radio = document.getElementById('cita' + cita.id) as HTMLInputElement;
            radio.checked = marcarPaquetes.checked;
            this.onCheckPaquete(cita);
        });
    }

    onSubmit(){
        this.citas = {} as PaginatedResponse;
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
