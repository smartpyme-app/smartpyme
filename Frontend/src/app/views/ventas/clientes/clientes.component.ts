import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-clientes',
  templateUrl: './clientes.component.html',
})
export class ClientesComponent implements OnInit {

    public clientes:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    public filtro:any = {};
    public producto:any = {};
    public filtrado:boolean = false;
    public categorias:any = [];
    modalRef!: BsModalRef;

    constructor( private apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('clientes').subscribe(clientes => { 
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('clientes/buscar/', this.buscador).subscribe(clientes => { 
                this.clientes = clientes;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public setEstado(cliente:any, activo:any):void{
        this.loading = true;
        cliente.activo = activo;
        this.apiService.store('cliente', cliente).subscribe(data => {
            this.loading = false;
            cliente = data;
            this.alertService.success('Guardado');
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public delete(cliente:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('cliente/', cliente.id) .subscribe(data => {
                for (let i = 0; i < this.clientes.data.length; i++) { 
                    if (this.clientes.data[i].id == data.id )
                        this.clientes.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.clientes.path + '?page='+ event.page).subscribe(clientes => { 
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros
    openFilter(template: TemplateRef<any>) {
        this.filtro.categoria_id = '';
        if(!this.categorias.lenght){
            this.apiService.getAll('categorias').subscribe(categorias => { 
                this.categorias = categorias;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('clientes/filtrar', this.filtro).subscribe(clientes => { 
            this.clientes = clientes;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
