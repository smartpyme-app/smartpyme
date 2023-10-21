import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';


@Component({
  selector: 'app-ventas',
  templateUrl: './ventas.component.html'
})

export class VentasComponent implements OnInit {

    public ventas:any = [];
    public venta:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public documentos:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('ventas').subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('ventas/buscar/', this.buscador).subscribe(ventas => { 
                this.ventas = ventas;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setEstado(venta:any, estado:string){
        venta.estado = estado;
        if (estado == 'Pagada') {
            venta.fecha_pago = this.apiService.date();
        }
        this.apiService.store('venta', venta).subscribe(venta => { 
            this.alertService.success('Venta ' + estado);
        }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('venta/', id) .subscribe(data => {
                for (let i = 0; i < this.ventas['data'].length; i++) { 
                    if (this.ventas['data'][i].id == data.id )
                        this.ventas['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('ventas/filtrar/' + filtro + '/', txt).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.ventas.path + '?page='+ event.page).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public reemprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    // Editar

    openModalEdit(template: TemplateRef<any>, venta:any) {
        this.venta = venta;
        
        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        this.modalRef = this.modalService.show(template);
    }

    public onSubmit() {
        this.loading = true;            
        this.apiService.store('venta', this.venta).subscribe(venta => {
            this.venta = {};
            this.modalRef.hide();
            this.loading = false;
            this.alertService.success("Guardado");
        },error => {this.alertService.error(error); this.loading = false; });

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
            this.apiService.store('usuarios/filtrar', {tipo: 'Vendedor'}).subscribe(usuarios => { 
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
        this.apiService.store('ventas/filtrar', this.filtro).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
