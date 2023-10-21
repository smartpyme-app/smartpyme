import { Component, OnInit,TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cliente-direcciones',
  templateUrl: './cliente-direcciones.component.html'
})
export class ClienteDireccionesComponent implements OnInit {

    public direcciones:any = [];
    public direccion:any = {};
    public countries:any = [];
    public cliente_id:number = 0;

    public loading = false;
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        const id = +this.route.snapshot.paramMap.get('id')!;
        this.cliente_id = id;
        if(isNaN(id))
            this.direcciones = [];
        else
            this.loadAll(id);
       
        this.apiService.getAll('countries').subscribe(countries => {
           this.countries = countries;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public loadAll(id:number){
        this.loading = true;
        this.apiService.getAll('cliente/' + id + '/direcciones').subscribe(direcciones => {
            this.direcciones = direcciones;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public openModal(template: TemplateRef<any>, direccion:any) {
        this.direccion = direccion;
        if(!direccion.id) {
            this.direccion.codigo_pais = 'US';
        }
        this.modalRef = this.modalService.show(template, {backdrop: 'static'});
    }

    public onSubmit():void{
        this.loading = true;
        this.direccion.cliente_id = this.cliente_id;
        this.apiService.store('cliente/direccion', this.direccion).subscribe(direccion => { 
            if(!this.direccion.id) {
               this.direcciones.unshift(direccion);
            }
            this.loading = false;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
