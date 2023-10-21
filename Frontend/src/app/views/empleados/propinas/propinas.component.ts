import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import * as moment from 'moment';

@Component({
  selector: 'app-propinas',
  templateUrl: './propinas.component.html'
})
export class PropinasComponent implements OnInit {

    public propinas: any = [];
    public usuarios: any = [];
    public sucursales: any = [];
    public loading = false;

    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

  	constructor( 
  	    private apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
  	) { }

  	ngOnInit() {

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.sucursal_id = '';
            this.filtro.usuario_id = '';
        }
        if(!this.usuarios.data){
            this.apiService.getAll('usuarios/filtrar/tipo/Mesero').subscribe(usuarios => { 
                this.usuarios = usuarios.data;
            }, error => {this.alertService.error(error); });
        }
        if(!this.sucursales.data){
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        }
  	    
        this.loadAll();

  	}

    public loadAll(){
        this.loading = true;
        this.apiService.store('propinas', this.filtro).subscribe(propinas => { 
            this.propinas = propinas;
            this.loading = false; this.filtrado = true;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
