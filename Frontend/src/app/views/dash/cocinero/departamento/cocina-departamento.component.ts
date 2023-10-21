import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-cocina-departamento',
  templateUrl: './cocina-departamento.component.html'
})
export class CocinaDepartamentoComponent implements OnInit {

    public departamento_id!:number;
    public dash:any = {};
    public dashResfresh:any;
    public comanda: any = {};
    public estado:string = '';
    public loading:boolean = false;

    modalRef!: BsModalRef;

    constructor( 
          private apiService: ApiService, private alertService: AlertService,
          private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.departamento_id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;
        this.apiService.getAll('dash/cocinero/departamento/' + this.departamento_id).subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
        
        this.dashResfresh = setInterval(()=> {
            if (!this.loading)
                this.loadAll();
        }, 25000);
    }


    public loadAll(){
        this.apiService.getAll('dash/cocinero/departamento/' + this.departamento_id).subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setEstadoDetalle(detalle:any, estado:string) {
          this.loading = true;
          detalle.estado = estado;
          this.apiService.store('comanda/detalle', detalle).subscribe(detalle => {
                this.loadAll();
                this.loading = false;
          },error => {this.alertService.error(error); this.loading = false; });
    }

    ngOnDestroy(){
        clearInterval(this.dashResfresh);

    }

}
