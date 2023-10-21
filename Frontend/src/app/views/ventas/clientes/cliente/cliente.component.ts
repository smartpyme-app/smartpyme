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
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('cliente/', id).subscribe(cliente => {
                this.cliente = cliente;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.cliente = {};
            this.cliente.empresa_id = this.apiService.auth_user().empresa_id;
            this.cliente.usuario_id = this.apiService.auth_user().id;
        }
    }

}
