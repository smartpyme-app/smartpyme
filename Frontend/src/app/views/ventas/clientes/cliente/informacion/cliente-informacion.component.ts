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
    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {
        const id = +this.route.snapshot.paramMap.get('id')!;
        if(isNaN(id)){
            this.cliente = {};
            this.cliente.empresa_id = this.apiService.auth_user().empresa_id;
            this.cliente.usuario_id = this.apiService.auth_user().id;
        }
        else{
            this.loadAll(id);
        }

    }

    public loadAll(id:number){
        this.loading = true;
        this.apiService.read('cliente/', id).subscribe(cliente => {
            this.cliente = cliente;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public submit():void{
        this.loading = true;
        
        console.log(this.cliente.etiquetas);

        this.apiService.store('cliente', this.cliente).subscribe(cliente => { 
            if(!this.cliente.id) {
               this.router.navigate(['/cliente/'+   cliente.id]);
            }
            this.cliente = cliente;
            this.loading = false;
            this.alertService.success('Guardado');
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
