import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';

@Component({
  selector: 'app-cliente-direccion',
  templateUrl: './cliente-direccion.component.html'
})
export class ClienteDireccionComponent implements OnInit {

  public direccion: any = {};
  public countries: any = [];
  public loading = false;
  @Output() direccionSelect = new EventEmitter();

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.apiService.getAll('countries').subscribe(countries => {
            this.countries = countries;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }


    public submit():void{
        this.loading = true;
        this.apiService.store('cliente/direccion', this.direccion).subscribe(direccion => { 
            this.direccionSelect.emit({direccion: this.direccion});
            this.loading = false;
            this.modalRef?.hide()
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
