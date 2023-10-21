import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-caja-chica-detalles',
  templateUrl: './caja-chica-detalles.component.html'
})
export class CajaChicaDetallesComponent implements OnInit {

    @Input() cajachica: any = {};
    public detalle:any = {};
    public cantidad!:number;
    public filtro:any = {};
    public filtrado:boolean = false;

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

    openModal(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;

        if (!this.detalle.id) {
            this.detalle.fecha = this.apiService.date();
            this.detalle.caja_id = this.cajachica.id;
            this.detalle.usuario_id = this.apiService.auth_user().id;
        }
        this.modalRef = this.modalService.show(template, {class: 'modal-xs'});
    }

    public onSubmit() {

        if (this.detalle.tipo == 'Salida' && this.cajachica.saldo < this.detalle.total) {
            this.alertService.info('El monto excede el disponible en caja')
        }
        else{
            this.loading = true;

            if (this.detalle.tipo == 'Salida') {
                this.detalle.salida = this.detalle.total;
                this.detalle.entrada = null;
                this.detalle.saldo = parseFloat(this.cajachica.saldo) - parseFloat(this.detalle.total);
            }
            if (this.detalle.tipo == 'Entrada') {
                this.detalle.entrada = this.detalle.total;
                this.detalle.salida = null;
                this.detalle.saldo = parseFloat(this.cajachica.saldo) + parseFloat(this.detalle.total);
            }
            

            this.apiService.store('caja-chica/detalle', this.detalle).subscribe(cajachica => {
                this.loading = false;
                this.cajachica.detalles.unshift(this.detalle);
                this.update.emit(this.cajachica);
                this.detalle = {};
                this.modalRef!.hide();
                this.alertService.success("Guardado");
            },error => {this.alertService.error(error); this.loading = false; });
        }

    }


    // Eliminar detalle
        public eliminarDetalle(detalle:any){
            if (confirm('Confirma que desea eliminar el elemento')) { 
                this.apiService.delete('caja-chica/detalle/', detalle.id).subscribe(detalle => {
                    for (var i = 0; i < this.cajachica.detalles.length; ++i) {
                        if (this.cajachica.detalles[i].id === detalle.id ){
                            this.cajachica.detalles.splice(i, 1);
                            this.update.emit(this.cajachica);
                        }
                    }
                },error => {this.alertService.error(error); this.loading = false; });
            }

        }

    // Filtros

    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.tipo = '';
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('caja-chica/detalles/filtrar', this.filtro).subscribe(detalles => { 
            this.cajachica.detalles = detalles;
            this.loading = false; this.filtrado = true;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
