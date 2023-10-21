import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';
import { SumPipe }     from '../../../../../pipes/sum.pipe';

@Component({
  selector: 'app-flete-detalles',
  templateUrl: './flete-detalles.component.html',
  providers: [ SumPipe ]
})
export class FleteDetallesComponent implements OnInit {

    @Input() flete: any = {};
    public detalle:any = {};

    @Output() update = new EventEmitter();
    modalRef!: BsModalRef;

    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {

    }

    openModal(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    setTotales(){
        this.flete.total_unidades = this.sumPipe.transform(this.flete.detalles, 'unidades');
        this.flete.total_bultos = this.sumPipe.transform(this.flete.detalles, 'bultos');
        this.flete.total_peso = this.sumPipe.transform(this.flete.detalles, 'peso');
        this.flete.total_valor_carga = this.sumPipe.transform(this.flete.detalles, 'valor_carga');
    }


    edit(detalle:any) {
        this.detalle = detalle;
        if (!this.detalle.id) {
            this.detalle.edit = true;
        }else{
            this.detalle.edit = false;
        }
    }

    public onSubmit(){
        if (!this.detalle.id && !this.detalle.edit) {
            this.flete.detalles.push(this.detalle);
        }
        this.detalle = {};
        this.update.emit(this.flete);
        this.modalRef.hide();
    }

    // Eliminar detalle
    public delete(detalle:any){
        if (confirm('Confirma que desea eliminar el detalle')) { 

            if(detalle.id) {
                this.apiService.delete('flete/detalle/', detalle.id).subscribe(detalle => {
                    for (var i = 0; i < this.flete.detalles.length; ++i) {
                        if (this.flete.detalles[i].id === detalle.id ){
                            this.flete.detalles.splice(i, 1);
                            this.update.emit(this.flete);
                        }
                    }
                },error => {this.alertService.error(error); this.loading = false; });
            }else{

                for (var i = 0; i < this.flete.detalles.length; ++i) {
                    if (this.flete.detalles[i].id === detalle.id ){
                        this.flete.detalles.splice(i, 1);
                        this.update.emit(this.flete);
                    }
                }
            }

        }
    }


}
