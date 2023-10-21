import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-proveedor',
  templateUrl: './proveedor.component.html'
})
export class ProveedorComponent implements OnInit {

    public proveedor:any = {};

    public loading = false;
    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {
        const id = +this.route.snapshot.paramMap.get('id')!;

        this.route.queryParams.subscribe(params => {
            console.log(params['estado']);
        });

        if(isNaN(id)){
            this.proveedor = {};
            this.proveedor.empresa_id = this.apiService.auth_user().empresa_id;
            this.proveedor.usuario_id = this.apiService.auth_user().id;
        }
        else{
            this.loadAll(id);
        }

    }

    public loadAll(id:number){
        this.loading = true;
        this.apiService.read('proveedor/', id).subscribe(proveedor => {
            this.proveedor = proveedor;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public submit():void{
        this.loading = true;
        
        console.log(this.proveedor.etiquetas);

        this.apiService.store('proveedor', this.proveedor).subscribe(proveedor => { 
            if(!this.proveedor.id) {
               this.router.navigate(['/proveedor/'+   proveedor.id]);
            }
            this.proveedor = proveedor;
            this.loading = false;
            this.alertService.success('Guardado');
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
