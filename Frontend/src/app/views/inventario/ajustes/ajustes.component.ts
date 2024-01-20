import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-ajustes',
  templateUrl: './ajustes.component.html',
})
export class AjustesComponent implements OnInit {

	public ajustes:any = [];
    public ajuste:any = {};
    public loading:boolean = false;
    public saving:boolean = false;

    public filtros:any = {};
    public productos:any = [];
    public sucursales:any = [];
    public usuarios:any = [];
    public producto:any = {};
    public sucursal:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.filtros.id_sucursal = '';
        this.filtros.id_producto = '';
        this.filtros.id_usuario = '';
        this.filtros.estado = '';
        this.filtros.search = '';
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('ajustes', this.filtros).subscribe(ajustes => { 
            this.ajustes = ajustes;
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
        this.apiService.paginate(this.ajustes.path + '?page='+ event.page, this.filtros).subscribe(ajustes => { 
            this.ajustes = ajustes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
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
        this.producto = this.productos.find((item:any) => item.id == this.ajuste.id_producto);
    }

    public setSucursal(){
        this.sucursal = this.producto?.inventarios.find((item:any) => item.id_sucursal == this.ajuste.id_sucursal);
        console.log(this.sucursal);
        this.ajuste.stock_actual = this.sucursal.stock;
    }

    public calAjuste(){
        this.ajuste.ajuste =  this.ajuste.stock_real - this.ajuste.stock_actual;
    }

    public openModal(template: TemplateRef<any>) {
        this.ajuste.id_producto = '';
        this.ajuste.id_sucursal = '';

        this.ajuste.id_usuario = this.apiService.auth_user().id;
        this.ajuste.id_empresa = this.apiService.auth_user().id_empresa;

        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error);});

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

}
