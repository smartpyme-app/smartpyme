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
    
    public filtro:any = {};
    public servicio:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
    public categorias:any = [];
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('servicios').subscribe(servicios => { 
            this.servicios = servicios;
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
                this.checkSucursales();
            }, error => {this.alertService.error(error); this.loading = false;});
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('servicios/buscar/', this.buscador).subscribe(servicios => { 
                this.servicios = servicios;
                this.loading = false; this.filtrado = true;
                this.checkSucursales();
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

    // sucursales

        public checkSucursales(){

            for(let i = 0; i < this.servicios.data.length; i++){            
                var servicio = this.servicios.data[i];
                servicio.lista_sucursales = JSON.parse(JSON.stringify(this.sucursales));

                for(let j = 0; j < servicio.sucursales.length; j++){
                    var servicio_sucursal = servicio.sucursales[j];
                    
                    for(let k = 0; k < servicio.lista_sucursales.length; k++){
                        var lista_sucursal = servicio.lista_sucursales[k];

                        if (lista_sucursal.id == servicio_sucursal.sucursal_id) {
                            lista_sucursal.agregado = true;
                        }

                    }

                }

            }

        }

        checked(servicio:any, sucursal:any){
            if(!sucursal.agregado) {
                this.addSucursal(servicio, sucursal);
            }else{
                this.deleteSucursal(servicio, sucursal);
            }

        }

        public addSucursal(servicio:any, sucursal:any){
            let item:any = {};
            item.servicio_id = servicio.id;
            item.activo = true;
            item.inventario = false;
            item.sucursal_id = sucursal.id;
            this.apiService.store('servicio/sucursal', item).subscribe(data => {
                servicio.sucursales.push(data);
                let sucursal = servicio.lista_sucursales.find((x:any) => x.id == data.sucursal_id);
                sucursal.agregado = true;
                this.alertService.success("Agregado");
            },error => {this.alertService.error(error); this.loading = false; });
        }

        public deleteSucursal(servicio:any, sucursal:any) {
            if (confirm('¿Desea eliminar el Registro?')) {
                let psucursal = servicio.sucursales.find((x:any) => x.sucursal_id == sucursal.id);
                this.apiService.delete('servicio/sucursal/', psucursal.id) .subscribe(data => {
                    this.loadAll();
                    this.alertService.success("Eliminado");
                }, error => {this.alertService.error(error); });
                       
            }

        }


    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.servicios.path + '?page='+ event.page).subscribe(servicios => { 
            this.servicios = servicios;
            this.checkSucursales();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros
    openFilter(template: TemplateRef<any>) {
        if(!this.filtrado) {
            this.filtro.sucursal_id = '';
            this.filtro.categoria_id = '';
        }


        if(!this.categorias.lenght){
            this.apiService.getAll('categorias').subscribe(categorias => { 
                this.categorias = categorias;
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
        this.apiService.store('servicios/filtrar', this.filtro).subscribe(servicios => { 
            this.servicios = servicios;
            this.checkSucursales();
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
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
            this.alertService.success("Datos guardados");
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false;
        });
    }

}
