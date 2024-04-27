import { Component, OnInit } from '@angular/core';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-register',
  templateUrl: './register.component.html'
})
export class RegisterComponent implements OnInit {

    public user: any = {};
    public paises: any = [];
    public loading = false;
    public saludo:string = '';
    public anio:any = '';
    public showpassword:boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
    ) { }

    ngOnInit() {
        this.user = this.apiService.register_user();

        this.apiService.getToUrl('https://restcountries.com/v3.1/all?order=name').subscribe(
        data => {
            this.paises = data;
            console.log(data);
        },
        error => {
            this.alertService.error(error);
            this.loading = false;
        });

        if(!this.user){
            this.user = {};
            this.user.empresa = {};

            this.user.empresa.industria = '';
            this.user.empresa.iva = 13; 
            this.user.empresa.plan = 'Emprendedor';
            this.user.empresa.tipo_plan = 'Mensual';
            this.user.empresa.pais = 'El Salvador';
            this.user.empresa.moneda = 'USD';
            this.user.empresa.total = 0;

            if (this.route.snapshot.queryParamMap.get('plan')!) {
                this.user.empresa.plan = this.route.snapshot.queryParamMap.get('plan')!;
            }

            if (this.route.snapshot.queryParamMap.get('tipo_plan')!) {
                this.user.empresa.tipo_plan = this.route.snapshot.queryParamMap.get('tipo_plan')!;
            }

            if (this.route.snapshot.queryParamMap.get('referido')!) {
                this.user.empresa.referido = this.route.snapshot.queryParamMap.get('referido')!;
            }

            if (this.route.snapshot.queryParamMap.get('campania')!) {
                this.user.empresa.campania = this.route.snapshot.queryParamMap.get('campania')!;
            }

            this.setPlan();

        }

    }

    public setPlan(){
        if(this.user.empresa.plan == 'Emprendedor'){
            this.user.empresa.user_limit = 1;
            this.user.empresa.sucursal_limit = 1;

            if(this.user.empresa.tipo_plan == 'Mensual'){
                this.user.empresa.total = 16.95;
            }else{
                this.user.empresa.total = 203.4;
            }
        }

        if(this.user.empresa.plan == 'Estándar'){
            this.user.empresa.user_limit = 2;
            this.user.empresa.sucursal_limit = 1;

            if(this.user.empresa.tipo_plan == 'Mensual'){
                this.user.empresa.total = 28.25;
            }else{
                this.user.empresa.total = 339;
            }
        }

        if(this.user.empresa.plan == 'Avanzado'){
            this.user.empresa.user_limit = 5;
            this.user.empresa.sucursal_limit = 2;

            if(this.user.empresa.tipo_plan == 'Mensual'){
                this.user.empresa.total = 56.5;
            }else{
                this.user.empresa.total = 678;
            }
        }

        if(this.user.empresa.plan == 'Pro'){
            this.user.empresa.user_limit = 5;
            this.user.empresa.sucursal_limit = 2;

            if(this.user.empresa.tipo_plan == 'Mensual'){
                this.user.empresa.total = 113;
            }else{
                this.user.empresa.total = 1220;
            }
        }

    }

    setModeda(){
        if(this.user.empresa.pais == 'El Salvador'){
            this.user.empresa.moneda = 'USD';
            this.user.empresa.iva = 13;
        }
        if(this.user.empresa.pais == 'Belice'){
            this.user.empresa.moneda = 'BZD';
            this.user.empresa.iva = 12.5;
        }
        if(this.user.empresa.pais == 'Guatemala'){
            this.user.empresa.moneda = 'GTQ';
            this.user.empresa.iva = 12;
        }
        if(this.user.empresa.pais == 'Honduras'){
            this.user.empresa.moneda = 'HNL';
            this.user.empresa.iva = 15;
        }
        if(this.user.empresa.pais == 'Nicaragua'){
            this.user.empresa.moneda = 'NIO';
            this.user.empresa.iva = 15;
        }
        if(this.user.empresa.pais == 'Costa Rica'){
            this.user.empresa.moneda = 'CRC';
            this.user.empresa.iva = 13;
        }
        if(this.user.empresa.pais == 'Panamá'){
            this.user.empresa.moneda = 'PAB';
            this.user.empresa.iva = 7;
        }
        console.log(this.user);
    }

    submit() {
        this.loading = true;

        this.apiService.register(this.user)
        .subscribe(
            data => {
                this.router.navigate(['/pago']);
                this.loading = false;
            },
            error => {
                this.alertService.error(error);
                this.loading = false;
            });
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  

}
