import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-repuestos',
  templateUrl: './repuestos.component.html',
})
export class RepuestosComponent implements OnInit {

    public repuestos:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    
    public filtro:any = {};
    public repuesto:any = {};
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
        this.apiService.getAll('repuestos').subscribe(repuestos => { 
            this.repuestos = repuestos;
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('repuestos/buscar/', this.buscador).subscribe(repuestos => { 
                this.repuestos = repuestos;
                this.loading = false; this.filtrado = true;
                this.checkSucursales();
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('repuesto/', id) .subscribe(data => {
                for (let i = 0; i < this.repuestos['data'].length; i++) { 
                    if (this.repuestos['data'][i].id == data.id )
                        this.repuestos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    // sucursales

        public checkSucursales(){

            for(let i = 0; i < this.repuestos.data.length; i++){            
                var repuesto = this.repuestos.data[i];
                repuesto.lista_sucursales = JSON.parse(JSON.stringify(this.sucursales));

                for(let j = 0; j < repuesto.sucursales.length; j++){
                    var repuesto_sucursal = repuesto.sucursales[j];
                    
                    for(let k = 0; k < repuesto.lista_sucursales.length; k++){
                        var lista_sucursal = repuesto.lista_sucursales[k];

                        if (lista_sucursal.id == repuesto_sucursal.sucursal_id) {
                            lista_sucursal.agregado = true;
                        }

                    }

                }

            }

        }

        checked(repuesto:any, sucursal:any){
            if(!sucursal.agregado) {
                this.addSucursal(repuesto, sucursal);
            }else{
                this.deleteSucursal(repuesto, sucursal);
            }

        }

        public addSucursal(repuesto:any, sucursal:any){
            let item:any = {};
            item.repuesto_id = repuesto.id;
            item.activo = true;
            item.inventario = false;
            item.sucursal_id = sucursal.id;
            this.apiService.store('repuesto/sucursal', item).subscribe(data => {
                repuesto.sucursales.push(data);
                let sucursal = repuesto.lista_sucursales.find((x:any) => x.id == data.sucursal_id);
                sucursal.agregado = true;
                this.alertService.success("Agregado");
            },error => {this.alertService.error(error); this.loading = false; });
        }

        public deleteSucursal(repuesto:any, sucursal:any) {
            if (confirm('¿Desea eliminar el Registro?')) {
                let psucursal = repuesto.sucursales.find((x:any) => x.sucursal_id == sucursal.id);
                this.apiService.delete('repuesto/sucursal/', psucursal.id) .subscribe(data => {
                    this.loadAll();
                    this.alertService.success("Eliminado");
                }, error => {this.alertService.error(error); });
                       
            }

        }


    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.repuestos.path + '?page='+ event.page).subscribe(repuestos => { 
            this.repuestos = repuestos;
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
        this.apiService.store('repuestos/filtrar', this.filtro).subscribe(repuestos => { 
            this.repuestos = repuestos;
            this.checkSucursales();
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    openModalPrecio(template: TemplateRef<any>, repuesto:any) {
        if(this.apiService.auth_user().tipo == 'Administrador') {
            this.repuesto = repuesto;
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        }

    }

    public onSubmit() {
        this.loading = true;
        // Guardamos la caja
        this.apiService.store('repuesto', this.repuesto).subscribe(repuesto=> {
            this.repuesto= {};
            this.alertService.success("Datos guardados");
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false;
        });
    }

}
