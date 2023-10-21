import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-empleado-metas',
  templateUrl: './empleado-metas.component.html'
})
export class EmpleadoMetasComponent implements OnInit {

    public meses: any = [];
    public metas: any = [];
    public ano:any;
    public meta: any = {};
    public loading = false;


    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router
    ) { }

    ngOnInit() {
        this.loadAll();
        this.meses = [{mes:'01', active:true, nombre:'Enero'}, {mes:'02', active:true, nombre:'Febrero'}, {mes:'03', active:true, nombre:'Marzo'}, {mes:'04', active:true, nombre:'Abril'},
                             {mes:'05', active:true, nombre:'Mayo'}, {mes:'06', active:true, nombre:'Junio'}, {mes:'07', active:true, nombre:'Julio'}, {mes:'08', active:true, nombre:'Agosto'}, 
                             {mes:'09', active:true, nombre:'Septiembre'}, {mes:'10', active:true, nombre:'Octubre'}, {mes:'11', active:true, nombre:'Noviembre'}, {mes:'12', active:true, nombre:'Diciembre'}];


    }

    public loadAll() {
        this.loading = true;

        this.apiService.read('empleado/metas/', +this.route.snapshot.paramMap.get('id')!).subscribe(metas => { 
            this.metas = metas;
            this.validarMetas();

            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public validarMetas(){
        let today = new Date();
        this.ano = today.getFullYear();

        for (var i = 0; i < this.meses.length; ++i) {

            for (var j = 0; j < this.metas.length; ++j) {
                if (this.metas[j].mes == this.meses[i].mes && this.metas[j].ano == this.ano) { 
                    this.meses[i].id = this.metas[j].id;
                    this.meses[i].active = false;
                    this.meses[i].meta = parseFloat(this.metas[j].meta);
                    this.meses[i].venta = parseFloat(this.metas[j].venta);
                }
            }
        }
    }

    public onSubmit(meta:any) {
        meta.empleado_id =  +this.route.snapshot.paramMap.get('id')!;
        meta.ano        =  this.ano;
        this.apiService.store('empleado/meta', meta).subscribe(meta => {
           meta = {};
           this.alertService.success("Meta guardada");
        },error => {this.alertService.error(error);});
    }

}
