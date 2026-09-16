import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { aplicarResumenPagoMultiple, resumenPagoMultiple } from '@utils/cambio-efectivo.util';

@Component({
    selector: 'app-metodos-de-pago',
    templateUrl: './metodos-de-pago.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, CurrencyPipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class MetodosDePagoComponent extends BaseModalComponent implements OnInit {

    @Input() venta: any = {};
    @Input() formaPagos: any = [];
    @Output() update = new EventEmitter();
    public pendiente = 0;
    public vuelto = 0;
    public puedeAplicar = false;

    constructor( 
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
    }

    public override openModal(template: TemplateRef<any>) {
        this.sumTotal();
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    public sumTotal(){
        const r = resumenPagoMultiple({ total: this.venta.total, formaPagos: this.formaPagos });
        this.formaPagos.total = r.recibido.toFixed(4);
        this.pendiente = r.pendiente;
        this.vuelto = r.vuelto;
        this.puedeAplicar = r.puedeAplicar;
        this.cdr.markForCheck();
    }

    public onSubmit(){
        const r = resumenPagoMultiple({ total: this.venta.total, formaPagos: this.formaPagos });
        if (!r.puedeAplicar) {
            return;
        }
        aplicarResumenPagoMultiple(this.venta, this.formaPagos, r);
        this.formaPagos.total = (r.recibido - r.vuelto).toFixed(4);
        this.update.emit();
        if (this.modalRef) {
            this.closeModal();
        }
    }

}
