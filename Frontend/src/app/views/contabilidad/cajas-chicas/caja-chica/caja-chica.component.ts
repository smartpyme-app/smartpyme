import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../../pipes/sum.pipe';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-caja-chica',
  templateUrl: './caja-chica.component.html'
})
export class CajaChicaComponent implements OnInit {

    public cajachica:any = {};
    public detalle:any = {};
    public cajachica_id:number = 0;

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

        this.cajachica_id = +this.route.snapshot.paramMap.get('id')!;
      
        if(isNaN(this.cajachica_id)){
            this.cajachica = {};
            this.cajachica.cliente = {};
            this.cajachica.cliente.nombre = '';
            this.cargarDatosIniciales();
        }
        else{
            this.cajachica.cliente = {};
            this.cajachica.cliente.nombre = '';
            this.loadAll();
        }

    }

    cargarDatosIniciales(){
        this.cajachica = {};
        this.cajachica.fecha = this.apiService.date();
        this.cajachica.cliente = {};
        this.cajachica.pagos = [];
        this.cajachica.prima = 0;
        this.detalle = {};
        this.cajachica.usuario_id = this.apiService.auth_user().id;
        this.cajachica.empresa_id = this.apiService.auth_user().empresa_id;
    }


    public loadAll(){
        this.loading = true;
        this.apiService.read('caja-chica/', this.cajachica_id).subscribe(cajachica => {
        this.cajachica = cajachica;
        this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }


    // Guardar cajachica
        public onSubmit() {

            this.loading = true;

            this.apiService.store('cajachica', this.cajachica).subscribe(cajachica => {
                this.loading = false;
                if(this.cajachica.id) {
                   this.router.navigate(['/cajachica/' + cajachica.id]);
                }else{
                    this.cargarDatosIniciales();
                    this.router.navigate(['/cajachica/nueva']);
                }
                this.alertService.success("Guardado");
            },error => {this.alertService.error(error); this.loading = false; });

        }

    public reporteMovimientos(){
        window.open(this.apiService.baseUrl + '/api/caja-chica/reporte/' + this.cajachica.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');

    }


}
