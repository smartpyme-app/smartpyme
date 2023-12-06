import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-servicios',
  templateUrl: './servicios.component.html',
})
export class ServiciosComponent implements OnInit {

    public servicios:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    
    public filtros:any = {};
    public servicio:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
    public categorias:any = [];
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.filtros.id_sucursal = '';
        this.filtros.id_categoria = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loadAll();

        this.apiService.getAll('categorias').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('servicios', this.filtros).subscribe(servicios => { 
            this.servicios = servicios;
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('servicios/buscar/', this.buscador).subscribe(servicios => { 
                this.servicios = servicios;
                this.loading = false; this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('servicio/', id) .subscribe(data => {
                for (let i = 0; i < this.servicios['data'].length; i++) { 
                    if (this.servicios['data'][i].id == data.id )
                        this.servicios['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.servicios.path + '?page='+ event.page, this.filtros).subscribe(servicios => { 
            this.servicios = servicios;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    onFiltrar(){
        this.loading = true;
        this.apiService.store('servicios/filtrar', this.filtros).subscribe(servicios => { 
            this.servicios = servicios;
            this.loading = false; this.filtrado = true;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    openModalPrecio(template: TemplateRef<any>, servicio:any) {
        if(this.apiService.auth_user().tipo == 'Administrador') {
            this.servicio = servicio;
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        }

    }

    public onSubmit() {
        this.loading = true;
        // Guardamos la caja
        this.apiService.store('servicio', this.servicio).subscribe(servicio=> {
            this.servicio= {};
            this.alertService.success('Servicio guardado', 'El servicio fue guardado exitosamente.');
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false;
        });
    }

}
