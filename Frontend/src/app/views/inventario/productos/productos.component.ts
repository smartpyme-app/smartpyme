import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-productos',
  templateUrl: './productos.component.html',
})
export class ProductosComponent implements OnInit {

    public productos:any = [];
    public loading:boolean = false;
    public downloading:boolean = false;
    public filtros:any = {};
    public producto:any = {};
    public bodegas:any = [];
    public categorias:any = [];
    public proveedores:any = [];
    public marcas:any = [];

    public ajuste:any = {};
    public inventario:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService, private router: Router, private route: ActivatedRoute
    ){}

    ngOnInit() {

        this.route.queryParams.subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                id_bodega: +params['id_bodega'] || '',
                id_categoria: +params['id_categoria'] || '',
                id_proveedor: +params['id_proveedor'] || '',
                id_sucursal: +params['id_sucursal'] || '',
                estado: params['estado'] || '',
                marca: params['marca'] || '',
                sin_stock: params['sin_stock'] || '',
                compuestos: params['compuestos'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            this.filtrarProductos();
        });

        if(this.route.snapshot.routeConfig?.path == 'producto-combos') this.verCombos();

        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('productos/marca-productos').subscribe(marcas => {
            this.marcas = marcas;
        }, error => {this.alertService.error(error); });

    }

    verCombos(){
        this.filtros.tipo = 'Compuesto';
        this.filtrarProductos();
    }

    public loadAll() {
        this.filtros.id_bodega = '';
        this.filtros.id_categoria = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_sucursal = '';
        this.filtros.marca = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.sin_stock = '';
        this.filtros.paginate = 10;
        this.filtros.page = 1;
        this.filtros.tipo = '';

        this.filtrarProductos();
    }

    public filtrarProductos(){
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;

        if(!this.filtros.sin_stock){
            this.filtros.sin_stock = '';
        }
        if(!this.filtros.id_categoria){
            this.filtros.id_categoria = '';
        }

        if(!this.filtros.marca){
            this.filtros.marca = '';
        }

        this.apiService.getAll('productos', this.filtros).subscribe(productos => {
            this.productos = productos;
            this.loading = false;
            if(this.modalRef){ this.modalRef.hide(); }
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setEstado(producto:any){
        this.apiService.store('producto', producto).subscribe(producto => {
            this.alertService.success('Producto actualizado', 'El producto fue guardado exitosamente.');
        }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/', id) .subscribe(data => {
                for (let i = 0; i < this.productos['data'].length; i++) {
                    if (this.productos['data'][i].id == data.id )
                        this.productos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });

        }

    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarProductos();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.filtros.page = event.page;
        this.filtrarProductos();
    }

    public onSubmit() {
        this.loading = true;
        this.apiService.store('producto', this.producto).subscribe(producto=> {
            this.producto = {};
            this.alertService.success('Producto guardado', 'El producto fue guardado exitosamente.');
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public openDescargar(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('productos/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'productos.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });

        this.modalRef = this.modalService.show(template);
    }

    public openModalAjuste(template: TemplateRef<any>, producto:any) {
       this.ajuste = {};
       this.producto = producto;
       this.inventario = this.producto.inventarios.find((item:any) => item.id_bodega == this.filtros.id_bodega);
       console.log(this.filtros);
       console.log(this.producto);
       this.ajuste.stock_actual = this.inventario.stock;
       this.alertService.modal = true;
       this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public calAjuste(){
        this.ajuste.ajuste = parseFloat(this.ajuste.stock_real) - parseFloat(this.ajuste.stock_actual);
    }

    public onSubmitAjuste() {
        this.loading = true;
        this.ajuste.id_producto = this.producto.id;
        this.ajuste.id_bodega = this.inventario.id_bodega;
        this.ajuste.id_empresa = this.apiService.auth_user().id_empresa;
        this.ajuste.id_usuario = this.apiService.auth_user().id;

        this.apiService.store('ajuste', this.ajuste).subscribe(ajuste => {
            // this.producto.inventarios[this.producto.inventarios.findIndex((item:any) => item.id_bodega == this.filtros.id_bodega)].stock = ajuste.stock_real;
            this.filtrarProductos();
            this.modalRef.hide();
            this.alertService.modal = false;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

    }


}
