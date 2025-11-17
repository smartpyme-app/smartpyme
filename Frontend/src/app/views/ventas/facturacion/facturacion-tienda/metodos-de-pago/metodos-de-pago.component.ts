import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-metodos-de-pago',
    templateUrl: './metodos-de-pago.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class MetodosDePagoComponent extends BaseModalComponent implements OnInit {

    @Input() venta: any = {};
    @Input() formaPagos: any = [];
    @Output() update = new EventEmitter();
    public aplicarCambios: boolean = false;

    constructor( 
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private sumPipe:SumPipe
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
    }

    public override openModal(template: TemplateRef<any>) {
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    public sumTotal(){
        this.formaPagos.total = (parseFloat(this.sumPipe.transform(this.formaPagos, 'total'))).toFixed(4);

        if((this.venta.total == 0) || ((parseFloat(this.formaPagos.total)).toFixed(2) != (parseFloat(this.venta.total)).toFixed(2))){
            this.aplicarCambios = true;
        }else{
            this.aplicarCambios = false;
        }

    }

    public onSubmit(){
        this.update.emit();
        if (this.modalRef) {
            this.closeModal();
        }
    }

}
