import { Component, OnInit } from '@angular/core';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PromocionalService } from '@services/promocional.service';


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
    public totalOriginal: number = 0;
    public tieneDescuento: boolean = false;
    public codigoPromocionalValido: boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
        public promocionalService: PromocionalService
    ) { }

    ngOnInit() {
        this.user = this.apiService.register_user();

        this.apiService.getToUrl('https://restcountries.com/v3.1/all?fields=name').subscribe(
        data => {
            this.paises = data;
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
            this.user.empresa.plan = '';
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

            if (this.route.snapshot.queryParamMap.get('promo')!) {
                this.user.empresa.codigo_promocional = this.route.snapshot.queryParamMap.get('promo')!;
            }

            this.setPlan();
            this.aplicarDescuento();
            
            // Inicializar tieneDescuento y codigoPromocionalValido si ya hay un código promocional
            const codigoPromo = this.promocionalService.obtenerCodigoPromocional(
                this.user.empresa.codigo_promocional
            );
            if (codigoPromo) {
                this.tieneDescuento = true;
                this.codigoPromocionalValido = true;
            }

        }

    }

    public setPlan(){
        if(this.user.empresa.plan == 1){//emprendedor
            this.user.empresa.user_limit = 1;
            this.user.empresa.sucursal_limit = 1;

            if(this.user.empresa.tipo_plan == 'Mensual'){
                this.totalOriginal = 16.95;
            }else{
                this.totalOriginal = 203.4;
            }
        }

        if(this.user.empresa.plan == 2){//estándar
            this.user.empresa.user_limit = 2;
            this.user.empresa.sucursal_limit = 1;

            if(this.user.empresa.tipo_plan == 'Mensual'){
                this.totalOriginal = 28.25;
            }else{
                this.totalOriginal = 339;
            }
        }

        if(this.user.empresa.plan == 3){//avanzado
            this.user.empresa.user_limit = 5;
            this.user.empresa.sucursal_limit = 2;

            if(this.user.empresa.tipo_plan == 'Mensual'){
                this.totalOriginal = 56.5;
            }else{
                this.totalOriginal = 678;
            }
        }

        if(this.user.empresa.plan == 4){//pro
            this.user.empresa.user_limit = 5;
            this.user.empresa.sucursal_limit = 2;

            if(this.user.empresa.tipo_plan == 'Mensual'){
                this.totalOriginal = 113;
            }else{
                this.totalOriginal = 1220;
            }
        }

        this.aplicarDescuento();
    }

    public aplicarDescuento(){
        const codigo = this.user.empresa.codigo_promocional;
        const codigoPromo = this.promocionalService.obtenerCodigoPromocional(codigo);
        
        if(codigoPromo){
            this.codigoPromocionalValido = true;
            
            if(this.totalOriginal > 0){
                // Aplicar descuento según el código promocional
                this.user.empresa.total = this.promocionalService.calcularPrecioConDescuento(
                    this.totalOriginal, 
                    codigo
                );
                this.tieneDescuento = true;
            }
        }else{
            this.codigoPromocionalValido = false;
            this.tieneDescuento = false;
            
            if(this.totalOriginal > 0){
                this.user.empresa.total = this.totalOriginal;
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

        // Guardar el total con descuento para mostrar después, pero enviar el original al backend
        const totalConDescuento = this.user.empresa.total;
        const totalOriginalParaEnviar = this.totalOriginal;
        
        // Enviar el total original al backend (sin descuento aplicado)
        // El backend aplicará el descuento basándose en el código promocional
        this.user.empresa.total = totalOriginalParaEnviar;

        this.apiService.register(this.user)
        .subscribe(
            data => {
                // Restaurar el total con descuento para la navegación
                this.user.empresa.total = totalConDescuento;
                
                // Si hay código promocional, agregarlo a la URL
                const navigationExtras: any = {};
                if (this.user.empresa.codigo_promocional) {
                    navigationExtras.queryParams = { promo: this.user.empresa.codigo_promocional };
                }
                this.router.navigate(['/pago'], navigationExtras);
                this.loading = false;
            },
            error => {
                // Restaurar el total con descuento en caso de error
                this.user.empresa.total = totalConDescuento;
                this.alertService.error(error);
                this.loading = false;
            });
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  

}

