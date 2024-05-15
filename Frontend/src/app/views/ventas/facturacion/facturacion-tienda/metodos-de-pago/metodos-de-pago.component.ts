import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-metodos-de-pago',
  templateUrl: './metodos-de-pago.component.html'
})
export class MetodosDePagoComponent implements OnInit {

    @Input() venta: any = {};
    @Input() formaPagos: any = [];
    @Output() update = new EventEmitter();
    modalRef!: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {
    }

    public openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    public sumTotal(){
        console.log(this.formaPagos);
        this.formaPagos.total = (parseFloat(this.sumPipe.transform(this.formaPagos, 'total'))).toFixed(4);
    }

    public onSubmit(){
        this.update.emit();
        this.modalRef.hide();
    }

}
