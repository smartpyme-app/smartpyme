import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-citas',
  templateUrl: './citas.component.html'
})

export class CitasComponent implements OnInit {

    public eventos:any = [];
    public evento:any = {};
    public usuarios:any = [];
    public productos:any = [];
    public clientes:any = [];
    public loading:boolean = false;
    public saving:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarEventos();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_cliente = '';
        this.filtros.id_usuario = '';
        this.filtros.id_canal = '';
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'inicio';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.filtrarEventos();
    }

    public filtrarEventos(){
        this.loading = true;
        this.apiService.getAll('eventos', this.filtros).subscribe(eventos => { 
            this.eventos = eventos;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public loadClientes(){
        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
        }, error => {this.alertService.error(error);});
    }

    public openModal(template: TemplateRef<any>, evento:any) {
        this.evento = evento;

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error);});


        if (!this.evento.id) {
            this.evento.id_empresa = this.apiService.auth_user().id_empresa;
            this.evento.id_usuario = this.apiService.auth_user().id;
            this.evento.frecuencia = '';
            this.evento.estado = 'Activo';
        }
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }

    public setEstado(evento:any){
        this.evento = evento;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('evento', this.evento).subscribe(evento => {
            if (!this.evento.id) {
                this.loadAll();
                this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
            }else{
                this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
            }
            this.saving = false;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.saving = false;});
    }


    public delete(evento:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('evento/', evento.id) .subscribe(data => {
                for (let i = 0; i < this.eventos.data.length; i++) { 
                    if (this.eventos.data[i].id == data.id )
                        this.eventos.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
