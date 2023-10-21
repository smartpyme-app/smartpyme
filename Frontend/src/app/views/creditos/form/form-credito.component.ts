import { Component, OnInit,TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../pipes/sum.pipe';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-form-credito',
  templateUrl: './form-credito.component.html'
})
export class FormCreditoComponent implements OnInit {

    @Input() venta: any = {};
    public credito:any = {};
    public loading = false;

    modalRef!: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
    }


    openModal(template: TemplateRef<any>) {

        this.credito.fecha = this.venta.fecha;
        this.credito.venta_id = this.venta.id;
        this.credito.total = this.venta.total;
        this.credito.prima = 0;
        this.credito.nombre_cliente = this.venta.nombre_cliente;
        this.credito.usuario_id = this.apiService.auth_user().id;
        this.credito.empresa_id = this.apiService.auth_user().empresa_id;
        
        this.modalRef = this.modalService.show(template, {class:'modal-md'});
        
    }


    // Guardar credito
    public onSubmit() {

        this.loading = true;

        this.apiService.store('credito', this.credito).subscribe(credito => {
            this.loading = false;
            this.credito = {};
            this.modalRef.hide();
            this.alertService.success("Guardado");
        },error => {this.alertService.error(error); this.loading = false; });

    }


}
