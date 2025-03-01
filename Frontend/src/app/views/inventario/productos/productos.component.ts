import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

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
    public connected:boolean = false;

    public ajuste:any = {};
    public inventario:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {

        this.loadAll();
        this.getUser();

        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bodegas/list').subscribe(bodegas => { 
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error); });
        
    }

    public getUser() {
   
        let user =JSON.parse(localStorage.getItem('SP_auth_user')!);
 
        if(user.woocommerce_status === 'connected'){
            this.connected = true;
           
        }
 
     }

    public loadAll() {
        this.filtros.id_bodega = '';
        this.filtros.id_categoria = '';
        this.filtros.id_proveedor = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.sin_stock = '';
        this.filtros.paginate = 10;

        this.filtrarProductos();
    }

    public filtrarProductos(){
        this.loading = true;

        if(!this.filtros.sin_stock){
            this.filtros.sin_stock = '';
        }

        if(!this.filtros.id_categoria){
            this.filtros.id_categoria = '';
        }

        this.apiService.getAll('productos', this.filtros).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
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
        this.apiService.paginate(this.productos.path + '?page='+ event.page, this.filtros).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
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

    public exportarWooCommerce(){
        Swal.fire({
            title: '¿Está seguro de exportar sus productos a WooCommerce?',
            html: `
                <p>Esta acción iniciará una migración asincrónica de productos a WooCommerce:</p>
                <ul style="text-align: left; margin-top: 1em;">
                    <li>Solo se migrarán los productos relacionados con su usuario y sucursal actual</li>
                    <li>Los productos vinculados a otras sucursales no serán exportados</li>
                    <li>El proceso se ejecutará en segundo plano y puede tomar varios minutos</li>
                    <li>Esta acción no se puede revertir</li>
                </ul>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, iniciar exportación',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Iniciando exportación...',
                    text: 'La migración de productos ha comenzado y continuará en segundo plano',
                    icon: 'info',
                    showConfirmButton: false,
                    timer: 3000
                });

                this.apiService.store('producto/exportar-woocommerce', {}).subscribe(
                    response => {
                        Swal.fire({
                            title: 'Proceso iniciado',
                            text: 'La migración de productos a WooCommerce se está ejecutando en segundo plano',
                            icon: 'success'
                        });
                    },
                    error => {
                        this.alertService.error(error);
                    }
                );
            }
        });
    }
    //descargarWooCommerce
    public descargarWooCommerce() {
        console.log('descargarWooCommerce');
        this.downloading = true;

        Swal.fire({
            title: 'Exportando productos a WooCommerce',
            text: 'Estamos preparando el archivo CSV con los productos de WooCommerce',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        this.apiService.export('productos/exportar/woocommerce', this.filtros).subscribe(
            (data: Blob) => {
                Swal.close();

                Swal.fire({
                    title: 'Exportando productos a WooCommerce',
                    text: 'El archivo CSV está listo para descargar',
                    icon: 'success',
                    showConfirmButton: true
                });
                const blob = new Blob([data], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = 'productos_woocommerce_' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                
                this.alertService.success('Exportación completada', 'El archivo CSV ha sido generado correctamente.');
            },
            (error) => { 
                this.alertService.error('Error en la exportación: ' + error); 
                this.downloading = false; 
            }
        );
    }


}
