import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';


declare var $:any;

@Component({
  selector: 'app-caja-cortes',
  templateUrl: './caja-cortes.component.html'
})

export class CajaCortesComponent implements OnInit {

    public cortes:any = [];
	public usuarios:any[] = [];
    public filtro:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    constructor(private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router)
    { }

    ngOnInit() {

        this.filtro = {};
        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();
        this.filtro.usuario_id = '';
        this.filtro.caja_id = +this.route.snapshot.paramMap.get('id')!;

        this.apiService.read('usuarios/caja/', this.filtro.caja_id).subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.loading = false;
            this.onFiltrar();
        }, error => {this.alertService.error(error); });

    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('cortes/filtrar', this.filtro).subscribe(cortes => { 
            this.cortes = cortes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public print(corte:any){
        window.open(this.apiService.baseUrl + '/api/corte/reporte/' + corte.id + '?token=' + this.apiService.auth_token(), 'Corte #' + corte.id, "top=50,left=300,width=600,height=500");
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.cortes.path + '?page='+ event.page).subscribe(cortes => { 
            this.cortes = cortes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
