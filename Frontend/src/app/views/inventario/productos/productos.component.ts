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
    public filtrosKardex:any = {
        fecha_inicio: '',
        fecha_fin: ''
    };
    public emailKardex: string = '';

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

    public descargarKardex(){
        this.loading = true;
        
        // Debug: verificar estructura de datos
        console.log('Estructura completa de productos:', this.productos);
        console.log('Productos.data:', this.productos.data);
        console.log('Tipo de productos.data:', typeof this.productos.data);
        console.log('Es array:', Array.isArray(this.productos.data));
        
        // Usar directamente los productos de la página actual
        const productoIds = this.productos.data.map((p: any) => p.id);
        console.log('Productos en página actual:', this.productos.data.length);
        console.log('IDs de productos a enviar:', productoIds);
        console.log('Tipo de productoIds:', typeof productoIds);
        console.log('Es array productoIds:', Array.isArray(productoIds));
        
        const filtrosConProductos = {
            producto_ids: productoIds.join(','), // Enviar como string separado por comas
            inicio: undefined, // Sin filtro de fecha
            fin: undefined // Sin filtro de fecha
        };
        
        console.log('Filtros con productos:', filtrosConProductos);
        console.log('Tipo de filtrosConProductos.producto_ids:', typeof filtrosConProductos.producto_ids);
        
        this.apiService.export('productos/kardex/exportar-filtrado', filtrosConProductos).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'kardex-filtrado.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.loading = false;
        }, (error) => { 
            this.alertService.error(error); 
            this.loading = false; 
        });
    }

    public openDescargarKardex(template: TemplateRef<any>) {
        // Cerrar el modal actual (modal de descarga)
        if (this.modalRef) {
            this.modalRef.hide();
        }
        
        // Resetear filtros de kardex
        this.filtrosKardex = {
            fecha_inicio: '',
            fecha_fin: ''
        };
        
        // Abrir el modal de kardex
        this.modalRef = this.modalService.show(template);
    }

    public descargarKardexConFiltros() {
        // Validar que las fechas estén completas
        if (!this.filtrosKardex.fecha_inicio || !this.filtrosKardex.fecha_fin) {
            this.alertService.error('Debe seleccionar fecha de inicio y fecha fin');
            return;
        }
        
        this.loading = true;
        
        // Usar directamente los productos de la página actual
        const productoIds = this.productos.data.map((p: any) => p.id);
        console.log('Productos en página actual:', this.productos.data.length);
        console.log('IDs de productos a enviar:', productoIds);
        
        const filtrosConProductos = {
            producto_ids: productoIds.join(','), // Enviar como string separado por comas
            inicio: this.filtrosKardex.fecha_inicio,
            fin: this.filtrosKardex.fecha_fin
        };
        
        console.log('Filtros con productos:', filtrosConProductos);
        
        this.apiService.export('productos/kardex/exportar-filtrado', filtrosConProductos).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'kardex-filtrado.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.loading = false;
            this.modalRef.hide();
        }, (error) => { 
            this.alertService.error(error); 
            this.loading = false; 
        });
    }

    public openDescargarKardexMasivo(template: TemplateRef<any>) {
        // Cerrar el modal actual (modal de descarga)
        if (this.modalRef) {
            this.modalRef.hide();
        }
        
        // Resetear email
        this.emailKardex = '';
        
        // Abrir el modal de kardex masivo
        this.modalRef = this.modalService.show(template);
    }

    public solicitarKardexMasivo() {
        // Validar email
        if (!this.emailKardex || !this.emailKardex.includes('@')) {
            this.alertService.error('Debe ingresar un correo electrónico válido');
            return;
        }
        
        this.loading = true;
        
        const datosSolicitud = {
            email: this.emailKardex,
            id_empresa: this.apiService.auth_user().id_empresa
        };
        
        this.apiService.store('productos/kardex/solicitar-masivo', datosSolicitud).subscribe((response: any) => {
            this.alertService.success('Solicitud registrada', 'Su solicitud ha sido registrada en la cola de procesamiento. Recibirá un correo electrónico cuando el kardex esté listo.');
            this.loading = false;
            this.modalRef.hide();
        }, (error) => { 
            this.alertService.error(error); 
            this.loading = false; 
        });
    }

    /**
     * Verifica si Shopify está activo en la empresa
     */
    public isShopifyActive(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa) return false;
        
        // Verificar si Shopify está configurado y conectado
        return !!(empresa.shopify_store_url && 
                 empresa.shopify_consumer_secret && 
                 empresa.shopify_status === 'connected');
    }


}
