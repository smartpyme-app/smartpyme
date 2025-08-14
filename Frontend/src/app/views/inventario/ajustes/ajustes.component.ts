import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Subject, Observable } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

@Component({
  selector: 'app-ajustes',
  templateUrl: './ajustes.component.html',
})
export class AjustesComponent implements OnInit {

	public ajustes:any = [];
    public ajuste:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;

    public filtros:any = {};
    public productos:any = [];
    public bodegas:any = [];
    public usuarios:any = [];
    public producto:any = {};
    public sucursal:any = {};
    public productosInput$ = new Subject<string>();
    public loadingProductos: boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService, private router: Router, private route: ActivatedRoute
    ){
        this.productosInput$.pipe(
            debounceTime(300),
            distinctUntilChanged(),
            switchMap(term => this.searchProductos(term))
        ).subscribe(productos => {
            this.productos = productos;
            this.loadingProductos = false;
        });
    }

    ngOnInit() {
        this.route.queryParams.subscribe(params => {
            this.filtros = {
                search: params['search'] || '',
                id_bodega: +params['id_bodega'] || '',
                id_producto: +params['id_producto'] || '',
                id_usuario: +params['id_usuario'] || '',
                id_sucursal: +params['id_sucursal'] || '',
                estado: params['estado'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            this.filtrarAjustes();
        });


        this.apiService.getAll('bodegas/list').subscribe(bodegas => { 
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error); });

    }

    private searchProductos(term: string): Observable<any[]> {
        if (!term || term.length < 2) {
            return of([]);
        }

        this.loadingProductos = true;
        return this.apiService.getAll('productos/list/search', { search: term, limit: 20 }).pipe(
            catchError(() => of([]))
        );
    }

    public loadAll() {
        this.filtros.id_bodega = '';
        this.filtros.id_producto = '';
        this.filtros.id_usuario = '';
        this.filtros.estado = '';
        this.filtros.search = '';
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.loading = true;
        this.filtrarAjustes();
    }

    public filtrarAjustes(){
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });
        this.loading = true;
        this.apiService.getAll('ajustes', this.filtros).subscribe(ajustes => { 
            this.ajustes = ajustes;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (columna === 'created_at') {
            if (this.filtros.orden === 'created_at') {
                this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
            } else {
                this.filtros.orden = 'created_at';
                this.filtros.direccion = 'desc'; // Por defecto, ordenar por fecha descendente (más reciente primero)
            }
        } else {
            if (this.filtros.orden === columna) {
                this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
            } else {
                this.filtros.orden = columna;
                this.filtros.direccion = 'asc';
            }
        }
        this.filtrarAjustes();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.filtros.page = event.page;
        this.filtrarAjustes();
    }

    public setEstado(ajuste:any){
        this.ajuste = ajuste;
        if (this.ajuste.estado == 'Cancelado') {
            if (confirm('¿Confirma cancelar el ajuste?')) {
                this.delete(this.ajuste.id);
            }
        }else{
            if (confirm('¿Confirma confirmar el ajuste?')) {
                this.onSubmit();
            }
        }
    }

    public setProducto(){
        if (!this.ajuste.id_producto) return;
        
        this.producto = this.productos.find((item:any) => item.id == this.ajuste.id_producto);
        if (this.producto) {
            this.ajuste.costo = this.producto.costo;
            // Si el producto no tiene inventarios cargados, cargarlos
            if (!this.producto.inventarios) {
                this.loadProductoInventarios(this.producto.id);
            }
        }
    }

    // Cargar inventarios de un producto específico
    private loadProductoInventarios(productoId: number) {
        this.apiService.getAll(`productos/${productoId}/inventarios`).subscribe(inventarios => {
            if (this.producto && this.producto.id == productoId) {
                this.producto.inventarios = inventarios;
            }
        }, error => {this.alertService.error(error);});
    }

    public setBodega(){
        this.sucursal = this.producto?.inventarios.find((item:any) => item.id_bodega == this.ajuste.id_bodega);
        // console.log(this.sucursal);
        this.ajuste.stock_actual = this.sucursal.stock;
    }

    public calAjuste(){
        this.ajuste.ajuste =  this.ajuste.stock_real - this.ajuste.stock_actual;
    }

    public openModal(template: TemplateRef<any>) {
        this.ajuste = {
            id_producto: '',
            id_bodega: '',
            id_usuario: this.apiService.auth_user().id,
            id_empresa: this.apiService.auth_user().id_empresa
        };
        
        this.productos = [];
        this.producto = {};

        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('productos/list').subscribe(productos => { 
            this.productos = productos;
        }, error => {this.alertService.error(error); });
        this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error); });
        this.modalRef = this.modalService.show(template);
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('ajuste', this.ajuste).subscribe(ajuste => { 
            this.ajuste = {};
            this.alertService.success('Ajuste guardado', 'El ajuste fue guardado exitosamente.');
            this.modalRef.hide();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(id:number) {
        this.saving = true;
        this.apiService.delete('ajuste/', id).subscribe(ajuste => { 
            this.ajuste = {};
            this.alertService.success('Ajuste eliminado', 'El ajuste fue eliminado exitosamente.');
            this.modalRef.hide();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    generarPartidaContable(ajuste:any){
        this.apiService.store('contabilidad/partida/ajuste', ajuste).subscribe(ajuste => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
        },error => {this.alertService.error(error);});
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('ajustes/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ajustes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

}
