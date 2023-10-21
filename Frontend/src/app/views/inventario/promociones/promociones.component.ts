import { Component, OnInit, TemplateRef  } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { FilterPipe }     from '../../../pipes/filter.pipe';

import * as moment from 'moment';

@Component({
  selector: 'app-promociones',
  templateUrl: './promociones.component.html',
  providers: [ FilterPipe ]
})
export class PromocionesComponent implements OnInit {

    public productos:any = [];
    public promociones:any = [];
    public promocionesFiltradas:any = [];
    public categorias:any = [];
    public categoria:any = {};
    public subcategorias:any = [];
    public subcategoria:any = {};
    public promocion:any = {};
    public filtro:any = {};
    public loading:boolean = false;
    
    public producto:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService, private filterPipe:FilterPipe
    ){}

    ngOnInit() {
        this.loadAll();

        this.filtro.nombre_producto = '';
        this.filtro.categoria = '';
        this.filtro.subcategoria = '';
        this.apiService.getAll('categorias').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('productos/promociones').subscribe(promociones => { 
            this.promociones = promociones;
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public onSelectCategoria(categoria:any){
        console.log(categoria);
        this.categoria = this.categorias.find((item:any) => item.nombre == categoria);
        console.log(this.categoria);
        this.subcategorias = this.categoria.subcategorias;
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/promocion/', id) .subscribe(data => {
                for (let i = 0; i < this.promociones.length; i++) { 
                    if (this.promociones[i].id == data.id )
                        this.promociones.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
        }
    }


    public deleteAll() {
        if (confirm('¿Desea eliminar totos los registros?')) {
            this.apiService.getAll('producto/promociones/eliminar') .subscribe(data => {
                this.loadAll();
            }, error => {this.alertService.error(error); });
        }
    }

    public openModalProductos(template: TemplateRef<any>) {
        this.promocionesFiltradas = this.promociones;
     
        this.promocionesFiltradas = this.filterPipe.transform(this.promociones, ['nombre_producto'], this.filtro.nombre_producto);
        this.promocionesFiltradas = this.filterPipe.transform(this.promocionesFiltradas, ['nombre_categoria'], this.filtro.categoria);
        this.promocionesFiltradas = this.filterPipe.transform(this.promocionesFiltradas, ['nombre_subcategoria'], this.filtro.subcategoria);

        console.log(this.promocionesFiltradas);

        this.promocion = {};
        this.promocion.inicio = moment().startOf('month').format('YYYY-MM-DDTHH:mm');
        this.promocion.fin = moment().endOf('month').format('YYYY-MM-DDTHH:mm');
        this.promocion.tipo_descuento = 'Porcentaje';
        this.promocion.descuento = 0;

        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    public openModalPromocion(template: TemplateRef<any>, promocion:any) {
        this.promocion = promocion;
        if (!this.promocion.id) {
            this.promocion.inicio = moment().startOf('month').format('YYYY-MM-DDTHH:mm');
            this.promocion.fin = moment().endOf('month').format('YYYY-MM-DDTHH:mm');
        }else{
            this.promocion.inicio = moment(this.promocion.inicio).format('YYYY-MM-DDTHH:mm');
            this.promocion.fin = moment(this.promocion.fin).format('YYYY-MM-DDTHH:mm');
        }

        this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
    }

    public loadProductos() {
        this.loading = true;
        this.apiService.getAll('productos/list').subscribe(productos => {
            this.promociones = [];
            for (let i = 0; i < productos.length; i++) { 
                let promocion:any = {};
                promocion.producto = {};
                promocion.producto_id = productos[i].id;
                promocion.nombre_producto = productos[i].nombre;
                promocion.producto.precio = productos[i].precio;
                promocion.nombre_categoria = productos[i].nombre_categoria;
                promocion.nombre_subcategoria = productos[i].nombre_subcategoria;

                if (productos[i].promocion) {
                    promocion.id = productos[i].promocion.id;
                    promocion.precio = productos[i].promocion.precio;
                    promocion.inicio = productos[i].promocion.inicio;
                    promocion.fin = productos[i].promocion.fin;
                }else{
                    // promocion.precio = productos[i].precio;
                }
                this.promociones.push(promocion);
            }
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public onSubmit() {
        this.loading = true;
        // Guardamos la caja
        this.apiService.store('producto/promocion', this.promocion).subscribe(promocion => {
            this.promocion = promocion;
            this.alertService.success("Promoción guardada");
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false;
        });
    }

    public generarPromociones() {
        this.loading = true;

        for (let i = 0; i < this.promocionesFiltradas.length; i++) { 

            if (this.promocion.tipo_descuento == 'Porcentaje') {
                this.promocionesFiltradas[i].precio = this.promocionesFiltradas[i].producto.precio - (this.promocionesFiltradas[i].producto.precio * (this.promocion.descuento / 100));
            }else{
                this.promocionesFiltradas[i].precio = this.promocionesFiltradas[i].producto.precio - this.promocion.descuento;
            }

            this.promocionesFiltradas[i].inicio = moment(this.promocion.inicio).format('YYYY-MM-DDTHH:mm');
            this.promocionesFiltradas[i].fin = moment(this.promocion.fin).format('YYYY-MM-DDTHH:mm');

            this.apiService.store('producto/promocion', this.promocionesFiltradas[i]).subscribe(promocion=> {
                if (this.promocionesFiltradas.length == i + 1) {
                    this.alertService.success((i + 1) + " promociones configuradas");
                    this.loading = false;
                    this.modalRef.hide();
                    this.loadAll();
                }
            },error => {this.alertService.error(error); this.loading = false;});
        }
    }

}
