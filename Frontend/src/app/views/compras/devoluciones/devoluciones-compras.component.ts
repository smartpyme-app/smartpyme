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
    public id_compra:any = null;
    public loading:boolean = false;
    public downloading:boolean = false;

    public proveedores:any = [];
    public usuarios:any = [];
    public comprasList:any = [];
    public sucursales:any = [];
    public filtros:any = {};

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
        this.filtros.inicio = null;
        this.filtros.fin = this.apiService.date();
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarCompras();
    }

    public filtrarCompras(){
        this.loading = true;
        this.apiService.getAll('devoluciones/compras', this.filtros).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
    }

    public setEstado(compra:any, estado:string){
        compra.estado = estado;
        this.apiService.store('compra', compra).subscribe(compra => { 
            this.alertService.success('Venta actualizada', 'La compra fue actualizada exitosamente.');
        }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('devolucion/compra/', id) .subscribe(data => {
                for (let i = 0; i < this.compras['data'].length; i++) { 
                    if (this.compras['data'][i].id == data.id )
                        this.compras['data'].splice(i, 1);
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

        this.filtrarCompras();
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

        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });


        this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error); });
        

        this.modalRef = this.modalService.show(template);
    }

    openModal(template: TemplateRef<any>) {
        this.id_compra = null;
        this.loading = true;
        this.apiService.getAll('compras/sin-devolucion').subscribe(compras => { 
            this.comprasList = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); });
        this.modalRef = this.modalService.show(template);
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('devoluciones/compras/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'devoluciones-compras.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }


}
