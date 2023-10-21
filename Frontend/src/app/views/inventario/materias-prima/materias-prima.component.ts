import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-materias-prima',
  templateUrl: './materias-prima.component.html',
})
export class MateriasPrimaComponent implements OnInit {

    public productos:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    
    public filtro:any = {};
    public producto:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
    public categorias:any = [];
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('materias-primas').subscribe(productos => { 
            this.productos = productos;
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
                this.checkSucursales();
            }, error => {this.alertService.error(error); this.loading = false;});
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('materias-primas/buscar/', this.buscador).subscribe(productos => { 
                this.productos = productos;
                this.loading = false; this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('materia-prima/', id) .subscribe(data => {
                for (let i = 0; i < this.productos['data'].length; i++) { 
                    if (this.productos['data'][i].id == data.id )
                        this.productos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    // sucursales

        public checkSucursales(){

            for(let i = 0; i < this.productos.data.length; i++){            
                var producto = this.productos.data[i];
                producto.lista_sucursales = JSON.parse(JSON.stringify(this.sucursales));

                for(let j = 0; j < producto.sucursales.length; j++){
                    var producto_sucursal = producto.sucursales[j];
                    
                    for(let k = 0; k < producto.lista_sucursales.length; k++){
                        var lista_sucursal = producto.lista_sucursales[k];

                        if (lista_sucursal.id == producto_sucursal.sucursal_id) {
                            lista_sucursal.agregado = true;
                        }

                    }

                }

            }

        }

        checked(producto:any, sucursal:any){
            if(!sucursal.agregado) {
                this.addSucursal(producto, sucursal);
            }else{
                this.deleteSucursal(producto, sucursal);
            }

        }

        public addSucursal(producto:any, sucursal:any){
            let item:any = {};
            item.producto_id = producto.id;
            item.activo = true;
            item.inventario = false;
            item.sucursal_id = sucursal.id;
            this.apiService.store('producto/sucursal', item).subscribe(data => {
                producto.sucursales.push(data);
                let sucursal = producto.lista_sucursales.find((x:any) => x.id == data.sucursal_id);
                sucursal.agregado = true;
                this.alertService.success("Agregado");
            },error => {this.alertService.error(error); this.loading = false; });
        }

        public deleteSucursal(producto:any, sucursal:any) {
            if (confirm('¿Desea eliminar el Registro?')) {
                let psucursal = producto.sucursales.find((x:any) => x.sucursal_id == sucursal.id);
                this.apiService.delete('producto/sucursal/', psucursal.id) .subscribe(data => {
                    this.loadAll();
                    this.alertService.success("Eliminado");
                }, error => {this.alertService.error(error); });
                       
            }

        }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.productos.path + '?page='+ event.page).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros
    openFilter(template: TemplateRef<any>) {
        this.filtro.categoria_id = '';
        if(!this.categorias.lenght){
            this.apiService.getAll('categorias').subscribe(categorias => { 
                this.categorias = categorias;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('materias-primas/filtrar', this.filtro).subscribe(productos => { 
            this.productos = productos;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    openModalPrecio(template: TemplateRef<any>, producto:any) {
        if(this.apiService.auth_user().tipo == 'Administrador') {
            this.producto = producto;
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        }

    }

    public onSubmit() {
        this.loading = true;
        // Guardamos la caja
        this.apiService.store('producto', this.producto).subscribe(producto=> {
            this.producto= {};
            this.alertService.success("Datos guardados");
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false;
        });
    }

}
