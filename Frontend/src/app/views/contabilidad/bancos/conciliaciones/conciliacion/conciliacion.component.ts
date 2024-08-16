import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-conciliacion',
  templateUrl: './conciliacion.component.html'
})
export class ConciliacionComponent implements OnInit {

    public conciliacion:any = {};
    public conciliacion_anterior:any = {};
    public cuentas:any = [];
    public transacciones:any = [];
    public filtros:any = {};
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {

        this.filtros.tipo = '';
        this.filtros.tipo_operacion = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 100000;

        this.loadAll();

        this.apiService.getAll('banco/cuentas/list').subscribe(cuentas => {
            this.cuentas = cuentas;
        }, error => {this.alertService.error(error);});

    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('banco/conciliacion/', id).subscribe(conciliacion => {
                this.conciliacion = conciliacion;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.conciliacion = {};
            this.conciliacion.id_cuenta = '';
            this.conciliacion.fecha = this.apiService.date();
            this.conciliacion.desde = this.apiService.date();
            this.conciliacion.hasta = this.apiService.date();
            this.conciliacion.saldo_anterior = 0;
            this.conciliacion.salidas = 0;
            this.conciliacion.entradas = 0;
            this.conciliacion.saldo_final = 0;
            this.conciliacion.diferencia = 0;
            this.conciliacion.id_empresa = this.apiService.auth_user().id_empresa;
            this.conciliacion.id_usuario = this.apiService.auth_user().id;
        }

    }

    public filtrarTransacciones(){
        this.loading = true;
        this.filtros.id_cuenta = this.conciliacion.id_cuenta;

        this.apiService.store('banco/ultima/conciliacion', this.filtros).subscribe(conciliacion => {
            this.conciliacion_anterior = conciliacion;
            
            this.conciliacion.desde = this.conciliacion_anterior.hasta;
            this.conciliacion.saldo_anterior = this.conciliacion_anterior.saldo_actual;

            this.apiService.getAll('bancos/transacciones', this.filtros).subscribe(transacciones => { 
                this.transacciones = transacciones;
                this.loading = false;

                // Filtrar los objetos con tipo 'Abono'
                const abonos = this.transacciones.data.filter((item:any) => item.tipo === 'Abono');
                const cargos = this.transacciones.data.filter((item:any) => item.tipo === 'Cargo');

                // Sumar los valores de los objetos filtrados
                this.conciliacion.entradas = abonos.reduce((sum:any, item:any) => sum + parseFloat(item.total), 0);
                this.conciliacion.salidas = cargos.reduce((sum:any, item:any) => sum + parseFloat(item.total), 0);

                console.log(this.conciliacion);

                this.total();

            }, error => {this.alertService.error(error); this.loading = false;});
        }, error => {this.alertService.error(error);});
    }

    public verificar(){
        this.total();
        this.conciliacion.diferencia = parseFloat(this.conciliacion.saldo_actual) - parseFloat(this.conciliacion.saldo_final)
    }

    public total(){
        this.conciliacion.saldo_final = parseFloat(this.conciliacion.saldo_anterior ? this.conciliacion.saldo_anterior : 0) 
                    + (parseFloat(this.conciliacion.entradas ? this.conciliacion.entradas : 0)
                    + parseFloat(this.conciliacion.otras_entradas ? this.conciliacion.otras_entradas : 0)) 
                    - (parseFloat(this.conciliacion.salidas ? this.conciliacion.salidas : 0) + parseFloat(this.conciliacion.impuestos ? this.conciliacion.impuestos : 0) + parseFloat(this.conciliacion.gastos ? this.conciliacion.gastos : 0));
    }

    public onSubmit(){

        if(this.conciliacion.diferencia != 0){
            this.alertService.info('Verifica la conciliación', 'La conciliación no esta cuadrada, hay una diferencia en los valores ingresados')
        }else{

            this.saving = true;

            this.apiService.store('banco/conciliacion', this.conciliacion).subscribe(conciliacion => {
                if (!this.conciliacion.id) {
                    this.alertService.success('Conciliación guardado', 'El conciliacion fue guardado exitosamente.');
                }else{
                    this.alertService.success('Conciliación creado', 'El conciliacion fue añadido exitosamente.');
                }
                this.router.navigate(['/bancos/conciliaciones']);
                this.saving = false;
            }, error => {this.alertService.error(error); this.saving = false;});
        }
    }

}
