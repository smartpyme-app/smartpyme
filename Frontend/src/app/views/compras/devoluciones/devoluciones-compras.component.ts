import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-devoluciones-compras',
  templateUrl: './devoluciones-compras.component.html'
})

export class DevolucionesComprasComponent implements OnInit {

    public compras:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.loading = true;
        this.filtro.inicio = null;
        this.filtro.fin = this.apiService.date();
        this.filtro.id_sucursal = '';
        this.filtro.estado = '';
        this.filtro.id_proveedor = '';
        this.filtro.id_usuario = '';

        this.apiService.getAll('devoluciones/compras').subscribe(compras => { 
            this.compras = compras;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('devoluciones/compras/buscar/', this.buscador).subscribe(compras => { 
                this.compras = compras;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setEstado(venta:any, estado:string){
        venta.estado = estado;
        this.apiService.store('venta', venta).subscribe(venta => { 
            this.alertService.success('Actualizado');
        }, error => {this.alertService.error(error); });
    }

    public descargar(){
        window.open(this.apiService.baseUrl + '/api/productos/export' + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('devolucion/venta/', id) .subscribe(data => {
                for (let i = 0; i < this.compras['data'].length; i++) { 
                    if (this.compras['data'][i].id == data.id )
                        this.compras['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('devoluciones/compras/filtrar/' + filtro + '/', txt).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.compras.path + '?page='+ event.page).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros

    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.sucursal_id = '';
            this.filtro.usuario_id = '';
            this.filtro.estado = '';
            this.filtro.metodo_pago = '';
            this.filtro.tipo_documento = '';
        }
        if(!this.usuarios.data){
            this.apiService.getAll('usuarios/filtrar/tipo/Mesero').subscribe(usuarios => { 
                this.usuarios = usuarios.data;
            }, error => {this.alertService.error(error); });
        }
        if(!this.sucursales.data){
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('devoluciones/compras/filtrar', this.filtro).subscribe(compras => { 
            this.compras = compras;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
