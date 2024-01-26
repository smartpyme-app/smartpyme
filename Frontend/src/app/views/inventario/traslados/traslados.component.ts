import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-traslados',
  templateUrl: './traslados.component.html',
})
export class TrasladosComponent implements OnInit {

    public traslados:any = [];
    public traslado:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;

    public filtros:any = {};
    public productos:any = [];
    public sucursales:any = [];
    public producto:any = {};
    public sucursalDe:any = {};
    public sucursalPara:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {

        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.id_sucursal_de = '';
        this.filtros.id_sucursal_para = '';
        this.filtros.id_producto = '';
        this.filtros.estado = '';
        this.filtros.search = '';
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarTraslados();
        
    }

    public filtrarTraslados(){
        this.apiService.getAll('traslados', this.filtros).subscribe(traslados => { 
            this.traslados = traslados;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.loadAll();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.traslados.path + '?page='+ event.page, this.filtros).subscribe(traslados => { 
            this.traslados = traslados;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setEstado(traslado:any){
        this.traslado = traslado;
        if (this.traslado.estado == 'Cancelado') {
            if (confirm('¿Confirma cancelar el traslado?')) {
                this.delete(this.traslado.id);
            }
        }else{
            if (confirm('¿Confirma confirmar el traslado?')) {
                this.onSubmit();
            }
        }
    }

    public setProducto(){
        this.producto = this.productos.find((item:any) => item.id == this.traslado.id_producto);
    }

    public setSucursalDe(){
        this.sucursalDe = this.producto?.inventarios.find((item:any) => item.id_sucursal == this.traslado.id_sucursal_de);
    }

    public setSucursalPara(){
        this.sucursalPara = this.producto?.inventarios.find((item:any) => item.id_sucursal == this.traslado.id_sucursal);
    }

    public openModal(template: TemplateRef<any>) {
        this.traslado.id_producto = '';
        this.traslado.id_sucursal = '';
        this.traslado.id_sucursal_de = '';

        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.traslado.id_empresa = this.apiService.auth_user().id_empresa;
        this.traslado.estado = 'Confirmado';

        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error);});
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('productos/list').subscribe(productos => { 
            this.productos = productos;
        }, error => {this.alertService.error(error); });
        this.modalRef = this.modalService.show(template);
    }

    public onSubmit() {
        this.saving = true;
        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.apiService.store('traslado', this.traslado).subscribe(traslado => { 
            this.traslado = {};
            this.alertService.success('Traslado realizado', 'El traslado fue añadido exitosamente.');
            this.modalRef.hide();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(id:number) {
        this.saving = true;
        this.apiService.delete('traslado/', id).subscribe(traslado => { 
            this.traslado = {};
            this.alertService.success('Traslado cancelado', 'El traslado fue cancelado exitosamente.');
            this.modalRef.hide();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('traslados/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'traslados.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

}
