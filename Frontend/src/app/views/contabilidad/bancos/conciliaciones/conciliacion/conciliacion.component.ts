import { Component, OnInit,TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';

@Component({
    selector: 'app-conciliacion',
    templateUrl: './conciliacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
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

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

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

        this.apiService.getAll('banco/cuentas/list')
          .pipe(this.untilDestroyed())
          .subscribe(cuentas => {
            this.cuentas = cuentas;
        }, error => {this.alertService.error(error);});

    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('banco/conciliacion/', id)
              .pipe(this.untilDestroyed())
              .subscribe(conciliacion => {
                this.conciliacion = conciliacion;
                console.log(this.conciliacion);
                
                
                setTimeout(() => {
                    this.filtrarTransacciones();
                }, 100);
                
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        } else {
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
        // Verificar que los campos necesarios estén llenos
        if (!this.conciliacion.id_cuenta || !this.conciliacion.desde || !this.conciliacion.hasta) {
            return;
        }

        this.loading = true;
        this.filtros.id_cuenta = this.conciliacion.id_cuenta;
        this.filtros.inicio = this.conciliacion.desde;
        this.filtros.fin = this.conciliacion.hasta;

        this.apiService.store('banco/ultima/conciliacion', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe(conciliacion => {
            this.conciliacion_anterior = conciliacion;
            if(this.conciliacion_anterior.hasta){
                this.conciliacion.desde = this.conciliacion_anterior.hasta;
            }
            this.conciliacion.saldo_anterior = this.conciliacion_anterior.saldo_actual || 0;

            this.apiService.getAll('bancos/transacciones', this.filtros)
              .pipe(this.untilDestroyed())
              .subscribe(transacciones => { 
                this.transacciones = transacciones;
                this.loading = false;

                this.conciliacion.entradas = 0;
                this.conciliacion.salidas = 0;

                if (this.transacciones && this.transacciones.data && this.transacciones.data.length > 0) {
                    const abonos = this.transacciones.data.filter((item:any) => item.tipo === 'Abono');
                    const cargos = this.transacciones.data.filter((item:any) => item.tipo === 'Cargo');

                    this.conciliacion.entradas = abonos.reduce((sum:any, item:any) => sum + parseFloat(item.total || 0), 0);
                    this.conciliacion.salidas = cargos.reduce((sum:any, item:any) => sum + parseFloat(item.total || 0), 0);
                }

                this.total();
                this.verificar();

            }, error => {this.alertService.error(error); this.loading = false;});
        }, error => {this.alertService.error(error);});
    }

    public verificar(){
        this.total();
        
        const saldoActual = typeof this.conciliacion.saldo_actual === 'string' 
            ? parseFloat(this.conciliacion.saldo_actual) || 0 
            : this.conciliacion.saldo_actual || 0;
        
        const saldoFinal = this.conciliacion.saldo_final || 0;
        
        this.conciliacion.diferencia = (saldoActual - saldoFinal).toFixed(2);
    }

    public total(){

        const saldoAnterior = typeof this.conciliacion.saldo_anterior === 'string' 
            ? parseFloat(this.conciliacion.saldo_anterior) || 0 
            : this.conciliacion.saldo_anterior || 0;
        
        const entradas = typeof this.conciliacion.entradas === 'string' 
            ? parseFloat(this.conciliacion.entradas) || 0 
            : this.conciliacion.entradas || 0;
        
        const otrasEntradas = typeof this.conciliacion.otras_entradas === 'string' 
            ? parseFloat(this.conciliacion.otras_entradas) || 0 
            : this.conciliacion.otras_entradas || 0;
        
        const salidas = typeof this.conciliacion.salidas === 'string' 
            ? parseFloat(this.conciliacion.salidas) || 0 
            : this.conciliacion.salidas || 0;
        
        const impuestos = typeof this.conciliacion.impuestos === 'string' 
            ? parseFloat(this.conciliacion.impuestos) || 0 
            : this.conciliacion.impuestos || 0;
        
        const gastos = typeof this.conciliacion.gastos === 'string' 
            ? parseFloat(this.conciliacion.gastos) || 0 
            : this.conciliacion.gastos || 0;
        
        this.conciliacion.saldo_final = saldoAnterior + 
                                       (entradas + otrasEntradas) - 
                                       (salidas + impuestos + gastos);
    }

    public onSubmit(){

        if(this.conciliacion.diferencia != 0){
            this.alertService.info('Verifica la conciliación', 'La conciliación no esta cuadrada, hay una diferencia en los valores ingresados')
        }else{

            this.saving = true;

            this.apiService.store('banco/conciliacion', this.conciliacion)
              .pipe(this.untilDestroyed())
              .subscribe(conciliacion => {
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

    getSaldoAnterior(): number {
        return this.normalizarNumero(this.conciliacion.saldo_anterior);
    }

    get totalSaldoFinal(): number {
        const saldoAnterior = this.normalizarNumero(this.conciliacion.saldo_anterior);
        const entradas = this.normalizarNumero(this.conciliacion.entradas);
        const otrasEntradas = this.normalizarNumero(this.conciliacion.otras_entradas);
        const salidas = this.normalizarNumero(this.conciliacion.salidas);
        const gastos = this.normalizarNumero(this.conciliacion.gastos);
        const impuestos = this.normalizarNumero(this.conciliacion.impuestos);
        return saldoAnterior + entradas + otrasEntradas - salidas - gastos - impuestos;
    }

    get totalEntradas(): number {
        const entradas = this.normalizarNumero(this.conciliacion.entradas);
        const otrasEntradas = this.normalizarNumero(this.conciliacion.otras_entradas);
        return entradas + otrasEntradas;
    }

    get totalSalidas(): number {
        const salidas = this.normalizarNumero(this.conciliacion.salidas);
        const gastos = this.normalizarNumero(this.conciliacion.gastos);
        const impuestos = this.normalizarNumero(this.conciliacion.impuestos);
        return salidas + gastos + impuestos;
    }
    
    private normalizarNumero(valor: any): number {
        if (valor === null || valor === undefined) return 0;
        if (typeof valor === 'number') return valor;
        if (typeof valor === 'string') return parseFloat(valor) || 0;
        return 0;
    }

}
