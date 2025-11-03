import { Component, OnInit,TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ClienteInformacionComponent } from './informacion/cliente-informacion.component';

@Component({
    selector: 'app-cliente',
    templateUrl: './cliente.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ClienteInformacionComponent],
    
})
export class ClienteComponent implements OnInit {

    public cliente:any = {};
    public loading = false;
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
                this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
                this.cliente.id_usuario = this.apiService.auth_user().id;
            }
        });
    }

}
