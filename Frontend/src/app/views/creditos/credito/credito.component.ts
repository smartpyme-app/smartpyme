import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../pipes/sum.pipe';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-credito',
  templateUrl: './credito.component.html'
})
export class CreditoComponent implements OnInit {

    public credito:any = {};
    public detalle:any = {};
    public credito_id:number = 0;

    public cliente:any = {};
    public mesas:any = [];
    public promociones:any = [];

    public loading = false;

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

      ngOnInit() {

        this.credito_id = +this.route.snapshot.paramMap.get('id')!;
      
        if(isNaN(this.credito_id)){
            this.credito = {};
            this.credito.cliente = {};
            this.credito.cliente.nombre = '';
            this.cargarDatosIniciales();
        }
        else{
            this.credito.cliente = {};
            this.credito.cliente.nombre = '';
            this.loadAll();
        }

    }

    cargarDatosIniciales(){
        this.credito = {};
        this.credito.fecha = this.apiService.date();
        this.credito.cliente = {};
        this.credito.pagos = [];
        this.credito.prima = 0;
        this.detalle = {};
        this.credito.usuario_id = this.apiService.auth_user().id;
        this.credito.empresa_id = this.apiService.auth_user().empresa_id;
    }


    public loadAll(){
        this.loading = true;
        this.apiService.read('credito/', this.credito_id).subscribe(credito => {
        this.credito = credito;
        this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }


    // Guardar credito
        public onSubmit() {

            this.loading = true;

            this.apiService.store('credito', this.credito).subscribe(credito => {
                this.loading = false;
                if(this.credito.id) {
                   this.router.navigate(['/credito/' + credito.id]);
                }else{
                    this.cargarDatosIniciales();
                    this.router.navigate(['/credito/nueva']);
                }
                this.alertService.success("Guardado");
            },error => {this.alertService.error(error); this.loading = false; });

        }

    public planDePagos(){
        window.open(this.apiService.baseUrl + '/api/reporte/credito/pagos/' + this.credito.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');

    }

    public planDeAmortizacion(){
        window.open(this.apiService.baseUrl + '/api/reporte/credito/plan-de-pagos/' + this.credito.total + '/'  + this.credito.numero_de_cuotas + '/'  + this.credito.forma_de_pago + '/'  + this.credito.interes_anual + '/'  + this.credito.id + '/' +  '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }




}
