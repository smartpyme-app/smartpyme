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
    public cliente:any = {};
    public loading:boolean = false;
    public saving:boolean = false;

    public filtros:any = {};
    public producto:any = {};
    public categorias:any = [];
    modalRef!: BsModalRef;

    constructor( private apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

    ngOnInit() {

        this.loadAll();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_categoria = '';
        this.filtros.buscador = '';
        this.filtros.estado = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarClientes();
    }

    public filtrarClientes(){
        this.loading = true;
        this.apiService.getAll('clientes', this.filtros).subscribe(clientes => { 
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
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

    public setTipo(cliente:any){
        this.cliente = cliente;
        this.onSubmit();
    }

    public setActivo(cliente:any, estado:any){
        this.cliente = cliente;
        this.cliente.enable = estado;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('cliente', this.cliente).subscribe(cliente => {
            this.cliente = {};
            this.saving = false;
            this.alertService.success('Cliente actualizado', 'El cliente fue actualizado exitosamente.');
        }, error => {this.alertService.error(error); this.saving = false;});
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


}
