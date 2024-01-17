import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-abonos-compras',
  templateUrl: './abonos-compras.component.html'
})

export class AbonosComprasComponent implements OnInit {

    public abonos:any = [];
    public abono:any = {};
    public loading:boolean = false;

    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public documentos:any = [];
    public filtros:any = {};
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

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCompras();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        
        this.filtrarCompras();
    }

    public filtrarCompras(){
        this.loading = true;
        this.apiService.getAll('compras/abonos', this.filtros).subscribe(abonos => { 
            this.abonos = abonos;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
    }

    public setEstado(cotizacion:any){
        this.apiService.store('orden-de-compra', cotizacion).subscribe(cotizacion => { 
            this.alertService.success('Orden de compra actualizada', 'La orden de compra fue actualizada exitosamente.');
        }, error => {this.alertService.error(error); });
    }
    
    public descargar(){
        window.open(this.apiService.baseUrl + '/api/productos/export' + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('orden-de-compra/', id) .subscribe(data => {
                for (let i = 0; i < this.abonos['data'].length; i++) { 
                    if (this.abonos['data'][i].id == data.id )
                        this.abonos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.abonos.path + '?page='+ event.page, this.filtros).subscribe(abonos => { 
            this.abonos = abonos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public reemprimir(abono:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + abono.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    // Editar

    openModalEdit(template: TemplateRef<any>, abono:any) {
        this.abono = abono;
        
        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        this.modalRef = this.modalService.show(template);
    }

    public onSubmit() {
        this.loading = true;            
        this.apiService.store('compra/abono', this.abono).subscribe(abono => {
            this.abono = {};
            this.modalRef.hide();
            this.loading = false;
            this.alertService.success('Abono guardado', 'El abono fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.loading = false; });

    }

    public openFilter(template: TemplateRef<any>) {
        
        this.modalRef = this.modalService.show(template);
    }


}
