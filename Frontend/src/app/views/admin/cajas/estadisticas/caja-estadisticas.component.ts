import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';


declare var $:any;

@Component({
  selector: 'app-caja-estadisticas',
  templateUrl: './caja-estadisticas.component.html'
})

export class CajaEstadisticasComponent implements OnInit {

    public estadisticas:any = [];
	public usuarios:any[] = [];
    public filtro:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    constructor(private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router)
    { }

    ngOnInit() {
        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();
        this.filtro.caja_id = +this.route.snapshot.paramMap.get('id')!;
        this.onFiltrar();
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('caja/estadisticas', this.filtro).subscribe(estadisticas => { 
            this.estadisticas = estadisticas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
