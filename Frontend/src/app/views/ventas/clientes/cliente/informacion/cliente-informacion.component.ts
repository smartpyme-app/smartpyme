import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cliente-informacion',
  templateUrl: './cliente-informacion.component.html'
})
export class ClienteInformacionComponent implements OnInit {

    public cliente:any = {};
    public loading = false;
    public saving = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.route.params.subscribe((params:any) => {
            if (params.id) {
                this.loading = true;
                this.apiService.read('cliente/', params.id).subscribe(cliente => {
                    this.cliente = cliente;
                    this.loading = false;
                }, error => {this.alertService.error(error); this.loading = false;});
            }else{
                this.cliente = {};
                this.cliente.tipo = 'Persona';
                this.cliente.tipo_contribuyente = '';
                this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
                this.cliente.id_usuario = this.apiService.auth_user().id;
            }
        });
    }

    public setTipo(tipo:any){
        this.cliente.tipo = tipo;
    }

    public submit():void{
        this.saving = true;

        this.apiService.store('cliente', this.cliente).subscribe(cliente => { 
            if (!this.cliente.id) {
                this.alertService.success('Cliente guardado', 'El cliente fue guardado exitosamente.');
            }else{
                this.alertService.success('Cliente creado', 'El cliente fue añadido exitosamente.');
            }
           this.router.navigate(['/clientes']);
            this.cliente = cliente;
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }


}
