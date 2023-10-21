import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../../pipes/sum.pipe';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-planilla',
  templateUrl: './planilla.component.html'
})
export class PlanillaComponent implements OnInit {

    public planilla:any = {};
    public detalle:any = {};

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

        this.planilla.id = +this.route.snapshot.paramMap.get('id')!;
      
        if(isNaN(this.planilla.id)){
            this.planilla = {};
            this.planilla.detalles = [];
            this.planilla.fecha = this.apiService.date();
            this.planilla.fecha_inicio = this.apiService.date();
            this.planilla.fecha_fin = this.apiService.date();
            this.planilla.estado = 'Pendiente';
            this.planilla.usuario_id = this.apiService.auth_user().id;
            this.planilla.empresa_id = this.apiService.auth_user().empresa_id;
        }
        else{
            this.loadAll();
        }

    }

    public sumTotal() {
        this.planilla.sueldo_base = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'sueldo_base'))).toFixed(2);
        this.planilla.horas_extras = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'horas_extras'))).toFixed(2);
        this.planilla.otros_ingresos = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'otros_ingresos'))).toFixed(2);
        this.planilla.comisiones = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'comisiones'))).toFixed(2);
        this.planilla.bonificaciones = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'bonificaciones'))).toFixed(2);
        this.planilla.vacaciones = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'vacaciones'))).toFixed(2);
        this.planilla.indemnizacion = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'indemnizacion'))).toFixed(2);
        this.planilla.aguinaldo = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'aguinaldo'))).toFixed(2);
        this.planilla.total_bruto = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'total_bruto'))).toFixed(2);
        
        this.planilla.isss = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'isss'))).toFixed(2);
        this.planilla.afp = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'afp'))).toFixed(2);
        this.planilla.renta = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'renta'))).toFixed(2);
        this.planilla.anticipos = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'anticipos'))).toFixed(2);
        this.planilla.prestamos = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'prestamos'))).toFixed(2);
        this.planilla.institucion_financiera = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'institucion_financiera'))).toFixed(2);
        this.planilla.otros_descuentos = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'otros_descuentos'))).toFixed(2);
        this.planilla.total = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'total'))).toFixed(2);
        // this.planilla.total_descuentos = (parseFloat(this.sumPipe.transform(this.planilla.detalles, 'total_descuento'))).toFixed(2);
    }

    public loadAll(){
        this.loading = true;
        this.apiService.read('planilla/', this.planilla.id).subscribe(planilla => {
        this.planilla = planilla;
        this.sumTotal();
        this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    updatePlanilla(planilla:any) {
        this.planilla = planilla;
        this.sumTotal();
    }

    // Guardar planilla
    public onSubmit() {

        this.loading = true;
        this.apiService.store('planilla/proceso', this.planilla).subscribe(planilla => {
            this.loading = false;
            this.router.navigate(['/planillas'])
            this.alertService.success("Guardado");
        },error => {this.alertService.error(error); this.loading = false; });

    }

    public pdfPlanilla(){
        var ventana = window.open(this.apiService.baseUrl + "/api/planilla/reporte/" + this.planilla.id + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    public pdfBoletas(){
        var ventana = window.open(this.apiService.baseUrl + "/api/planilla/boletas/" + this.planilla.id + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }


}
