import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-activo',
  templateUrl: './activo.component.html'
})
export class ActivoComponent implements OnInit {

    public activo:any = {};
    public categorias:any = [];
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
            this.apiService.read('activo/', id).subscribe(activo => {
                this.activo = activo;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.activo = {};
            this.activo.estado = 'En uso';
            this.activo.fecha_compra = this.apiService.date();
            this.activo.empresa_id = this.apiService.auth_user().empresa_id;
            this.activo.sucursal_id = this.apiService.auth_user().sucursal_id;
            this.activo.responsable_id = this.apiService.auth_user().id;
            this.activo.usuario_id = this.apiService.auth_user().id;
        }

        this.apiService.getAll('activos/categorias').subscribe(categorias => {
            this.categorias = categorias;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setCategoria(categoria:any){
        this.categorias.push(categoria);
        this.activo.categoria_id = categoria.id;
    }

    public onSubmit(){
        this.loading = true;
        this.apiService.store('activo', this.activo).subscribe(activo => { 
            this.router.navigate(['/activos']);
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
