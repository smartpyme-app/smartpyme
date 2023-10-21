import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-metas',
  templateUrl: './metas.component.html'
})
export class MetasComponent implements OnInit {

    public empleados: any = [];
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

        this.apiService.getAll('empleados/list').subscribe(empleados => { 
            this.empleados = empleados;

            for (var i = 0; i < this.empleados.length; ++i) {
                this.apiService.read('empleado/metas/', this.empleados[i].id).subscribe(metas => { 
                    this.empleados[i].metas = metas;
                    this.validarMetas(i);

                    this.loading = false;
                }, error => {this.alertService.error(error); this.loading = false;});
            }

        }, error => {this.alertService.error(error); });

    }

    public validarMetas(item:any){
        let today = new Date();
        this.ano = today.getFullYear();

        for (var i = 0; i < this.meses.length; ++i) {

            for (var j = 0; j < this.empleados[i].metas.length; ++j) {
                if (this.empleados[item].metas[j].mes == this.meses[i].mes && this.empleados[item].metas[j].ano == this.ano) { 
                    this.empleados[item].meses[i].id = this.empleados[item].metas[j].id;
                    this.empleados[item].meses[i].active = false;
                    this.empleados[item].meses[i].meta = parseFloat(this.empleados[item].metas[j].meta);
                    this.empleados[item].meses[i].venta = parseFloat(this.empleados[item].metas[j].venta);
                }
            }
        }
    }

    public onSubmit(meta:any) {
        meta.empleado_id =  1;
        meta.ano        =  this.ano;
        this.apiService.store('empleado/meta', meta).subscribe(meta => {
           meta = {};
           this.alertService.success("Meta guardada");
        },error => {this.alertService.error(error);});
    }

}
