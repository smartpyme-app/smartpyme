import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';


@Component({
  selector: 'app-mantenimientos',
  templateUrl: './mantenimientos.component.html'
})

export class MantenimientosComponent implements OnInit {

    public mantenimientos:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    public proveedores:any = [];
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
        this.apiService.getAll('mantenimientos').subscribe(mantenimientos => { 
            this.mantenimientos = mantenimientos;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('mantenimientos/buscar/', this.buscador).subscribe(mantenimientos => { 
                this.mantenimientos = mantenimientos;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setEstado(mantenimiento:any, estado:string){
        this.apiService.read('mantenimiento/', mantenimiento.id).subscribe(mantenimiento => {
                mantenimiento.estado = estado;
                this.apiService.store('mantenimiento/facturacion', mantenimiento).subscribe(mantenimiento => { 
                    this.alertService.success('Actualizado');
                }, error => {this.alertService.error(error); });
                this.loading = false;
        });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('mantenimiento/', id) .subscribe(data => {
                for (let i = 0; i < this.mantenimientos['data'].length; i++) { 
                    if (this.mantenimientos['data'][i].id == data.id )
                        this.mantenimientos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('mantenimientos/filtrar/' + filtro + '/', txt).subscribe(mantenimientos => { 
            this.mantenimientos = mantenimientos;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.mantenimientos.path + '?page='+ event.page).subscribe(mantenimientos => { 
            this.mantenimientos = mantenimientos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public imprimir(mantenimiento:any){
        window.open(this.apiService.baseUrl + '/api/mantenimiento/imprimir/' + mantenimiento.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

    // Filtros

    openFilter(template: TemplateRef<any>) {

        if(!this.filtrado) {
            this.filtro.inicio = null;
            this.filtro.fin = null;
            this.filtro.proveedor_id = '';
            this.filtro.estado = '';
        }
        if(!this.proveedores.length){
            this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
                this.proveedores = proveedores;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('mantenimientos/filtrar', this.filtro).subscribe(mantenimientos => { 
            this.mantenimientos = mantenimientos;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
