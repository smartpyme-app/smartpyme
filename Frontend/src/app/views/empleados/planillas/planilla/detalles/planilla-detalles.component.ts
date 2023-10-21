import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-planilla-detalles',
  templateUrl: './planilla-detalles.component.html'
})
export class PlanillaDetallesComponent implements OnInit {

    @Input() planilla: any = {};
    public detalle:any = {};
    public empleados:any = [];

    @Output() update = new EventEmitter();
    modalRef?: BsModalRef;

    public buscador:string = '';
    public loading:boolean = false;

    @ViewChild('mempleados')
    public empleadosTemplate!: TemplateRef<any>;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.loading = true;
        this.apiService.getAll('empleados/list').subscribe(empleados => {
            this.empleados = empleados;
            this.loading = false;
            if (!this.planilla.id) {
                this.modalRef = this.modalService.show(this.empleadosTemplate, {class: 'modal-lg', backdrop: 'static', keyboard: false});
                // this.generarPlanilla();
            }
        }, error => {this.alertService.error(error); this.loading = false; });

    }
    public selectedAll(){
        for (const empleado of this.empleados) {
            empleado.selected = !empleado.selected;
        }
    }

    public generarPlanilla(){
               
        for (var i = 0; i < this.empleados.length; ++i) {
            if (this.empleados[i].selected) {
                // code...
                this.detalle.empleado_id = this.empleados[i].id;
                this.detalle.nombre_empleado = this.empleados[i].nombre;

                this.detalle.dias_trabajados = this.empleados[i].dias_trabajados;
                this.detalle.horas_trabajadas = this.empleados[i].horas_trabajadas;
                this.detalle.tipo_salario = this.empleados[i].tipo_salario;
                this.detalle.sueldo_base = parseFloat(this.empleados[i].sueldo) + parseFloat(this.empleados[i].total_fletes) ;
                this.detalle.horas_extras = 0;
                this.detalle.comisiones = parseFloat(this.empleados[i].total_comisiones);
                this.detalle.otros_ingresos = 0;
                this.detalle.bonificaciones = 0;
                this.detalle.vacaciones = 0;
                this.detalle.indemnizacion = 0;
                this.detalle.aguinaldo = 0;

                this.detalle.calcular_afp = this.empleados[i].afp;
                this.detalle.calcular_isss = this.empleados[i].isss;
                this.detalle.calcular_renta = this.empleados[i].renta;
                
                this.calcular(this.detalle);

                this.detalle.anticipos = 0;
                this.detalle.prestamos = 0;
                this.detalle.institucion_financiera = 0;
                this.detalle.otros_descuentos = 0;
                

                this.detalle.total_bruto = this.detalle.horas_extras +
                    this.detalle.comisiones +
                    this.detalle.otros_ingresos +
                    this.detalle.bonificaciones +
                    this.detalle.vacaciones +
                    this.detalle.indemnizacion +
                    this.detalle.aguinaldo +
                    this.detalle.sueldo_base;

                this.detalle.total_descuentos = this.detalle.isss +
                    this.detalle.afp +
                    this.detalle.renta +
                    this.detalle.anticipos +
                    this.detalle.prestamos +
                    this.detalle.institucion_financiera +
                    this.detalle.otros_descuentos;

                this.detalle.total = this.detalle.total_bruto - this.detalle.total_descuentos;
                this.planilla.detalles.push(this.detalle);
                this.detalle = {};
            }
        }
        this.update.emit(this.planilla);
        this.modalRef!.hide();
    }

    calDeducciones(){
        if (this.detalle.sueldo_base) {
            let empleado = this.empleados.find((item:any) => item.id == this.detalle.empleado_id);
            this.calcular(this.detalle);
            this.sumTotales();
        }
    }

    sumTotales(){
        if (this.detalle.sueldo_base) {
            this.detalle.total_bruto = this.detalle.horas_extras +
                this.detalle.comisiones +
                this.detalle.otros_ingresos +
                this.detalle.bonificaciones +
                this.detalle.vacaciones +
                this.detalle.indemnizacion +
                this.detalle.aguinaldo +
                this.detalle.sueldo_base;

            this.detalle.total_descuentos = this.detalle.isss +
                this.detalle.afp +
                this.detalle.renta +
                this.detalle.anticipos +
                this.detalle.prestamos +
                this.detalle.institucion_financiera +
                this.detalle.otros_descuentos;

            this.detalle.total = this.detalle.total_bruto - this.detalle.total_descuentos;
            this.update.emit(this.planilla);
        }
        
    }

    calcular(detalle:any) {
        var dividirEntre = 1;

        if (detalle.tipo_salario == 'Semanal') {
            dividirEntre = 4;
        }
        else if (detalle.tipo_salario == 'Quincenal') {
            dividirEntre = 2;
        }
        else if (detalle.tipo_salario == 'Mensual') {
            dividirEntre = 1;
        }

        const salario = parseFloat(detalle.sueldo_base);
        const porcentajeAFP = 0.0725;
        const limite = 7045.06;
        const baseAFP = parseFloat((limite * porcentajeAFP).toFixed(2));

        const baseAfpPatronal = 545.99;
        const baseIsssPatronal = 75;
        
        if(salario == 0)return;
        
        var netoMensual = 0;
        var afp_patronal = ((salario * 0.0775)) > baseAfpPatronal/dividirEntre ? baseAfpPatronal/dividirEntre : salario * 0.0775;
        var isss_patronal = (salario * 0.075) > baseIsssPatronal/dividirEntre ? baseIsssPatronal/dividirEntre : salario * 0.075;
        var AFP = (salario * porcentajeAFP) > baseAFP/dividirEntre ? baseAFP/dividirEntre : salario * porcentajeAFP;

        var salarioAFP = salario - AFP;
        const ISSS = ((salario * 0.03)) > 30 ? 30/dividirEntre : salario * 0.03;
        const renta = this.calcularRenta(salario, AFP, ISSS, detalle.tipo_salario)
        var descuentoT = AFP + ISSS + renta;
        netoMensual = salario - AFP - ISSS - renta;
        console.log(detalle);
        if (detalle.calcular_afp) {
            this.detalle.afp_patronal = parseFloat(afp_patronal.toFixed(2));
            this.detalle.afp = parseFloat(AFP.toFixed(2));
        }else{
            this.detalle.afp_patronal = 0;
            this.detalle.afp = 0;
        }

        if (detalle.calcular_afp) {
            this.detalle.isss_patronal = parseFloat(isss_patronal.toFixed(2));
            this.detalle.isss = parseFloat(ISSS.toFixed(2));
        }else{
            this.detalle.isss_patronal = 0;
            this.detalle.isss = 0;
        }


        if (detalle.calcular_renta) {
            this.detalle.renta = parseFloat(renta.toFixed(2));
        }else{
            this.detalle.renta = 0;
        }

        this.detalle.salario = parseFloat(salarioAFP.toFixed(2));
        // this.detalle.descuentoT = descuentoT.toFixed(2);
        // this.detalle.salarioNeto = netoMensual.toFixed(2);
        
        if(detalle.tipo_salario == 'Vacaciones'){
            const vacaciones = Number(salario) * 0.3;
            const vacacionesT = vacaciones + Number(salario);
            detalle.vacaciones = vacaciones.toFixed(2);
        }
    }

    calcularRenta(salario:number, AFP:number, ISSS:number, tipo_salario:string) {

        var CF1 = 0;
        var CF2 = 0;
        var CF3 = 0;
        var EX1 = 0;
        var EX2 = 0;
        var EX3 = 0;

        var H1 = 0;
        var H2 = 0;
        var H3 = 0;
        
        var RF = 0;
        
        if (tipo_salario == 'Semanal') {
            CF1 = 4.42;
            CF2 = 15;
            CF3 = 72.14;
            EX1 = 118;
            EX2 = 223.81;
            EX3 = 509.52;
            H1 = 118;
            H2 = 223.81;
            H3 = 509.52;
        }
        else if (tipo_salario == 'Quincenal') {
            CF1 = 8.83;
            CF2 = 30;
            CF3 = 144.28;
            EX1 = 236;
            EX2 = 447.62;
            EX3 = 1019.05;
            H1 = 236;
            H2 = 447.62;
            H3 = 1019.05;
        }
        else if (tipo_salario == 'Mensual') {
            CF1 = 17.67;
            CF2 = 60;
            CF3 = 288.57;
            EX1 = 472;
            EX2 = 895;
            EX3 = 2038.10;
            H1 = 472;
            H2 = 895.24;
            H3 = 2038.10;
        }

        const rentaAgravada = salario - AFP -ISSS;
            if (rentaAgravada > 0 && rentaAgravada <= H1) {
                return 0;
            }
            else if (rentaAgravada > H1 && rentaAgravada <= H2) {
                var r = rentaAgravada - EX1;
                RF = (r * 0.1) + CF1;
            }
            else if (rentaAgravada > H2 && rentaAgravada <= H3) {
                var r = rentaAgravada - EX2;
                RF = (r * 0.2) + CF2;
            }
            else if (rentaAgravada > H3) {
                var r = rentaAgravada - EX3;
                RF = (r * 0.3) + CF3;
            }
        return RF;
    }


    openModal(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, { class:'modal-lg', backdrop: 'static'});
    }

    // Agregar detalle
    onSubmit():void{
        this.loading = true;
        this.detalle.total = parseFloat(this.detalle.sueldo) + parseFloat(this.detalle.extras) + parseFloat(this.detalle.otros) - parseFloat(this.detalle.renta) - parseFloat(this.detalle.afp) - parseFloat(this.detalle.isss);
        this.apiService.store('planilla/detalle', this.detalle).subscribe(detalle => {
            if (!this.detalle.id)
                this.planilla.detalles.push(this.detalle);
            this.update.emit(this.planilla);
            this.modalRef?.hide();
            this.alertService.success("Guardado");
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });

    }


    // Eliminar detalle
        public eliminarDetalle(detalle:any){
            if (confirm('Confirma que desea eliminar el elemento')) { 
                // this.apiService.delete('planilla/detalle/', detalle.id).subscribe(detalle => {
                    for (var i = 0; i < this.planilla.detalles.length; ++i) {
                        if (this.planilla.detalles[i].empleado_id === detalle.empleado_id ){
                            this.planilla.detalles.splice(i, 1);
                            this.update.emit(this.planilla);
                        }
                    }
                // },error => {this.alertService.error(error); this.loading = false; });
            }

        }


}
