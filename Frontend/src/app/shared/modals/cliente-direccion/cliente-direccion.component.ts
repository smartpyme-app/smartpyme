import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-cliente-direccion',
    templateUrl: './cliente-direccion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class ClienteDireccionComponent extends BaseModalComponent implements OnInit {

  public direccion: any = {};
  public countries: any = [];
  public override loading = false;
  @Output() direccionSelect = new EventEmitter();

    constructor( 
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.apiService.getAll('countries')
            .pipe(this.untilDestroyed())
            .subscribe(countries => {
            this.countries = countries;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    override openModal(template: TemplateRef<any>) {
        super.openModal(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public submit():void{
        this.loading = true;
        this.apiService.store('cliente/direccion', this.direccion)
            .pipe(this.untilDestroyed())
            .subscribe(direccion => { 
            this.direccionSelect.emit({direccion: this.direccion});
            this.loading = false;
            this.closeModal()
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
