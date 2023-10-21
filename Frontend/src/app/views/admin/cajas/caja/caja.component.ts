import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-caja',
  templateUrl: './caja.component.html'
})

export class CajaComponent implements OnInit {

    public caja:any = {};
    public documento:any = {};
    public sucursales:any = [];
    public loading:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService, 
        private modalService: BsModalService, private route: ActivatedRoute, private router: Router)
    { }

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('sucursales').subscribe(sucursales => { 
            this.sucursales = sucursales;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.loading = true;
        const caja_id = +this.route.snapshot.paramMap.get('id')!;
        this.apiService.read('caja/', caja_id).subscribe(caja => { 
            this.caja = caja;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    openModal(template: TemplateRef<any>, documento:any) {
        this.documento = documento;
        this.modalRef = this.modalService.show(template);
    }

    public onSubmit() {
        this.loading = true;
        // Guardamos la caja
        this.apiService.store('documento', this.documento).subscribe(documento => {
            this.documento = documento;
            this.modalRef.hide();
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public onSubmitCaja() {
        this.loading = true;
        // Guardamos la caja
        this.apiService.store('caja', this.caja).subscribe(caja => {
            this.caja.id = caja.id;
            this.loading = false;
            this.alertService.success("Datos guardados");
        },error => {this.alertService.error(error); this.loading = false; });
    }

}
