import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cliente',
  templateUrl: './cliente.component.html'
})
export class ClienteComponent implements OnInit {

    public cliente:any = {};
    public loading = false;
    public returnUrl = '/clientes';
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.route.queryParams.subscribe(params => {
            const url = params['returnUrl'];
            if (url && typeof url === 'string' && this.isAllowedReturnUrl(url)) {
                this.returnUrl = url;
            } else {
                this.returnUrl = '/clientes';
            }
        });
        this.loadAll();
    }

    private isAllowedReturnUrl(url: string): boolean {
        return url.startsWith('/cliente/vista-360') || url === '/clientes';
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
