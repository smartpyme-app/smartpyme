import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-devoluciones-ventas',
  templateUrl: './devoluciones-ventas.component.html'
})

export class DevolucionesVentasComponent implements OnInit {

    public ventas:any = [];
    public id_venta:any = null;
    public loading:boolean = false;
    public downloading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public ventasList:any = [];
    public sucursales:any = [];
    public filtros:any = {};

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
        this.filtros.inicio = null;
        this.filtros.fin = this.apiService.date();
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.id_cliente = '';
        this.filtros.id_usuario = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarVentas();
    }

    public filtrarVentas(){
        this.loading = true;
        if(this.filtros.id_cliente == null){
            this.filtros.id_cliente = '';
        }
        this.apiService.getAll('devoluciones/ventas', this.filtros).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
    }

    public setEstado(venta:any, estado:string){
        venta.estado = estado;
        this.apiService.store('venta', venta).subscribe(venta => { 
            this.alertService.success('Venta actualizada', 'La venta fue actualizada exitosamente.');
        }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('devolucion/venta/', id) .subscribe(data => {
                for (let i = 0; i < this.ventas['data'].length; i++) { 
                    if (this.ventas['data'][i].id == data.id )
                        this.ventas['data'].splice(i, 1);
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

        this.filtrarVentas();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.ventas.path + '?page='+ event.page).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros

    openFilter(template: TemplateRef<any>) {     

        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });


        this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error); });
        

        this.modalRef = this.modalService.show(template);
    }

    openModal(template: TemplateRef<any>) {
        this.id_venta = null;
        this.loading = true;
        this.apiService.getAll('ventas/sin-devolucion').subscribe(ventas => { 
            this.ventasList = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); });
        this.modalRef = this.modalService.show(template);
    }

    public imprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/devolucion/facturacion/impresion/' + venta.id + '?token=' + this.apiService.auth_token());
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('devoluciones/ventas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'devoluciones-ventas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }


}
