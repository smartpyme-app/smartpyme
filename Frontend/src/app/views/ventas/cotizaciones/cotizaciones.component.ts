import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-cotizaciones',
  templateUrl: './cotizaciones.component.html'
})

export class CotizacionesComponent implements OnInit {

    public cotizaciones:any = [];
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

        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.loading = true;
        this.filtro.estado = '';
        this.filtro.id_cliente = '';
        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();

        this.apiService.getAll('cotizaciones').subscribe(cotizaciones => { 
            this.cotizaciones = cotizaciones;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('cotizaciones/buscar/', this.buscador).subscribe(cotizaciones => { 
                this.cotizaciones = cotizaciones;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setEstado(venta:any){
        this.apiService.store('venta', venta).subscribe(venta => { 
            this.alertService.success('Actualizado');
        }, error => {this.alertService.error(error); });
    }
    
    public descargar(){
        window.open(this.apiService.baseUrl + '/api/productos/export' + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('venta/', id) .subscribe(data => {
                for (let i = 0; i < this.cotizaciones['data'].length; i++) { 
                    if (this.cotizaciones['data'][i].id == data.id )
                        this.cotizaciones['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('cotizaciones/filtrar/' + filtro + '/', txt).subscribe(cotizaciones => { 
            this.cotizaciones = cotizaciones;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.cotizaciones.path + '?page='+ event.page).subscribe(cotizaciones => { 
            this.cotizaciones = cotizaciones;
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


    onFiltrar(){
        this.loading = true;
        this.apiService.store('cotizaciones/filtrar', this.filtro).subscribe(cotizaciones => { 
            this.cotizaciones = cotizaciones;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
