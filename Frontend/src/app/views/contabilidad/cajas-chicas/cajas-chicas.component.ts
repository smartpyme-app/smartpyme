import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';


@Component({
  selector: 'app-cajas-chicas',
  templateUrl: './cajas-chicas.component.html'
})

export class CajasChicasComponent implements OnInit {

    public cajaschicas:any = [];
    public cajachica:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    public usuarios:any = [];
    public sucursales:any = [];

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('cajas-chicas').subscribe(cajaschicas => { 
            this.cajaschicas = cajaschicas;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('cajas-chicas/buscar/', this.buscador).subscribe(cajaschicas => { 
                this.cajaschicas = cajaschicas;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }
    }

    openModal(template: TemplateRef<any>, cajachica:any) {     
        this.cajachica = cajachica;
        if (!this.cajachica.id) {
            this.cajachica.fecha = this.apiService.date();
        }

        if(!this.usuarios.data){
            this.apiService.getAll('usuarios').subscribe(usuarios => { 
                this.usuarios = usuarios.data;
            }, error => {this.alertService.error(error); });
        }
        if(!this.sucursales.data){
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        }

        this.modalRef = this.modalService.show(template);
    }


    public onSubmit(){

        this.loading = true;
        // this.cajachica.usuario_id = this.apiService.auth_user().id;
        // this.cajachica.sucursal_id = this.apiService.auth_user().sucursal_id;
        this.apiService.store('caja-chica', this.cajachica).subscribe(cajachica => { 
            this.loadAll();
            this.cajachica = {};
            this.modalRef.hide();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('caja-chica/', id) .subscribe(data => {
                for (let i = 0; i < this.cajaschicas['data'].length; i++) { 
                    if (this.cajaschicas['data'][i].id == data.id )
                        this.cajaschicas['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.cajaschicas.path + '?page='+ event.page).subscribe(cajaschicas => { 
            this.cajaschicas = cajaschicas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }



}
