import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../services/api.service';
import { AlertService } from '../../../../services/alert.service';

@Component({
  selector: 'app-credito-pagos',
  templateUrl: './credito-pagos.component.html'
})
export class CreditoPagosComponent implements OnInit {

    @Input() credito: any = {};
    public pago:any = {};
    public cantidad!:number;

    @Output() update = new EventEmitter();
    modalRef?: BsModalRef;

    public buscador:string = '';
    public loading:boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>, pago:any) {
        this.pago = pago;

        if (!this.pago.id) {
            this.pago.fecha = this.apiService.date();
            this.pago.cuota = this.credito.cuota;
            if (this.credito.mora) {
                this.pago.mora = this.calcularMora(100, 0.05, 1);
            }
            this.pago.credito_id = this.credito.id;
            this.pago.metodo_pago = 'Efectivo';
            this.pago.usuario_id = this.apiService.auth_user().id;
            this.onCalcular();
            this.pago.cuota = Math.round((this.credito.cuota + Number.EPSILON) * 100) / 100;
            this.pago.saldo_inicial = this.credito.saldo;
            this.pago.saldo_inicial = this.credito.saldo;
        }
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    onCalcular(){
        this.pago.interes_mensual = (this.credito.interes_anual / 12 )/ 100;
        this.pago.interes = this.credito.saldo * this.pago.interes_mensual;
        this.pago.abono = this.pago.cuota - this.pago.interes;
        this.pago.saldo_final = this.credito.saldo - this.pago.abono;
    }

    public onSubmit() {

        this.loading = true;

        this.apiService.store('credito/pago', this.pago).subscribe(credito => {
            this.loading = false;
            this.credito.pagos.push(this.pago);
            this.update.emit(this.credito);
            this.pago = {};
            this.modalRef!.hide();
            this.alertService.success("Guardado");
        },error => {this.alertService.error(error); this.loading = false; });

    }

    public calcularMora(saldoVencido:any, tasaInteresMoratoriaMensual:any, mesesMora:any) {
        // Calcula los intereses moratorios
        const interesesMoratorios = saldoVencido * tasaInteresMoratoriaMensual * mesesMora;

        // Calcula el monto total de la mora sumando los intereses atrasados al saldo vencido
        const moraTotal = saldoVencido + interesesMoratorios;

        return interesesMoratorios;
    }


    // Eliminar pago
        public eliminarDetalle(pago:any){
            if (confirm('Confirma que desea eliminar el elemento')) { 
                this.apiService.delete('credito/pago/', pago.id).subscribe(pago => {
                    for (var i = 0; i < this.credito.pagos.length; ++i) {
                        if (this.credito.pagos[i].id === pago.id ){
                            this.credito.pagos.splice(i, 1);
                            this.update.emit(this.credito);
                        }
                    }
                },error => {this.alertService.error(error); this.loading = false; });
            }

        }

    public imprimirDoc(pago:any){
        window.open(this.apiService.baseUrl + '/api/reporte/credito/pago/' + pago.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');

    }

}
