import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { inventariosParaStockVenta } from '@shared/utils/inventario-venta.util';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';

import * as moment from 'moment';
import { LazyImageDirective } from '../../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-tienda-venta-citas',
    templateUrl: './tienda-venta-citas.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule, NgSelectModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class TiendaVentaCitasComponent extends BasePaginatedModalComponent implements OnInit {

    @Input() venta: any = {};
    @Output() productoSelect = new EventEmitter();

    public citas: PaginatedResponse<any> = {} as PaginatedResponse;
    public clientes:any = [];
    public detalle:any = {};
    public detalles:any = [];
    public override filtros:any = {};
    public buscador:any = '';

    constructor( 
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private sumPipe:SumPipe,
        private cdr: ChangeDetectorRef
    ) {
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.citas;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.citas = data;
    }

    ngOnInit() {

    }

    override openModal(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe(clientes => { 
            this.clientes = clientes;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); });
        this.citas = {} as PaginatedResponse;
        this.loadAll();
        super.openModal(template, { class: 'modal-xl', backdrop: 'static' });        
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
        this.cdr.markForCheck();
    }

    public filtrarCitas(){
        this.loading = true;
        this.cdr.markForCheck();
        this.venta.id_cliente = this.filtros.id_cliente;
        this.apiService.getAll('eventos', this.filtros).pipe(this.untilDestroyed()).subscribe(citas => { 
            this.citas = citas;
            
            this.detalles = [];
            let radio = document.getElementById('marcarPaquetes') as HTMLInputElement;
            radio.checked = false;

            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

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
                    detalle.porcentaje_impuesto = producto.porcentaje_impuesto ?? this.apiService.auth_user()?.empresa?.iva;
                    if(producto.tipo != 'Servicio' && producto.inventarios.length > 0){
                        producto.inventarios = inventariosParaStockVenta(producto.inventarios, this.venta);
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
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck();});
            });

        }else{
            
            this.detalles = this.detalles.filter((item:any) => item.id_cita !== cita.id);
            this.cdr.markForCheck();
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
        this.cdr.markForCheck();
    }

    onSubmit(){
        this.citas = {} as PaginatedResponse;
        this.productoSelect.emit(this.detalle);
        if(this.modalRef){
            this.closeModal();
        }
        this.cdr.markForCheck();
    }

    agregarDetalles(){
        for (let i = 0; i < this.detalles.length; i++) { 
            this.productoSelect.emit(this.detalles[i]);
        }

        if(this.modalRef){
            this.closeModal();
        }
        this.cdr.markForCheck();
    }


}
