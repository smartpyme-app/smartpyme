import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
  selector: 'app-caja-documentos',
  templateUrl: './caja-documentos.component.html'
})

export class CajaDocumentosComponent implements OnInit {

    @Input() caja:any = {};
    public documento:any = {};
    public loading:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService, 
        private modalService: BsModalService, private route: ActivatedRoute, private router: Router)
    { }

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>, documento:any) {
        this.documento = documento;
        this.modalRef = this.modalService.show(template);
    }

    public onSubmit() {
        this.loading = true;
        this.documento.caja_id = this.caja.id;
        this.apiService.store('documento', this.documento).subscribe(documento => {
            if (!this.documento.id) {
                this.caja.documentos.push(documento);
            }
            this.documento = {};
            this.modalRef.hide();
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('documento/', id) .subscribe(data => {
                for (let i = 0; i < this.caja.documentos.length; i++) { 
                    if (this.caja.documentos[i].id == data.id )
                        this.caja.documentos.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }


}
