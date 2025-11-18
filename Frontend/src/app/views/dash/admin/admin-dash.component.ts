import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { DatosComponent } from './datos/datos.component';
import { TopsComponent } from './tops/tops.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

import * as moment from 'moment';

@Component({
    selector: 'app-admin-dash',
    templateUrl: './admin-dash.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, DatosComponent, TopsComponent],
    
})
export class AdminDashComponent extends BaseModalComponent implements OnInit {

    public dash:any = {};
    public sucursales:any[] = [];
    public filtro:any = {};
    public saludo:string = '';
    public usuario:any = {};
    public override loading:boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }


    ngOnInit() {

        this.saludo  = this.apiService.saludar();
        this.usuario  = this.apiService.auth_user();
        this.filtro.inicio  = this.apiService.date();
        this.filtro.fin     = this.apiService.date();
        this.filtro.id_sucursal = this.apiService.auth_user().id_sucursal;
        
        this.filtro.time = 'day';
        this.filtro.inicio = moment().startOf(this.filtro.time).format('YYYY-MM-DD');
        this.filtro.fin = moment().endOf(this.filtro.time).format('YYYY-MM-DD');
        this.onFiltrar();

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });


    }

    public setTime($time:any){
        this.filtro.time = $time;
        this.filtro.inicio = moment().startOf(this.filtro.time).format('YYYY-MM-DD');
        this.filtro.fin = moment().endOf(this.filtro.time).format('YYYY-MM-DD');
        this.onFiltrar();
    }

    public override openModal(template: TemplateRef<any>, config?: any) {
        super.openModal(template, config);
    } 
    
    public onFiltrar(){     
        this.loading = true;
        this.apiService.getAll('dash', this.filtro)
          .pipe(this.untilDestroyed())
          .subscribe(dash => { 
            this.dash = dash;
            this.loading = false;
            if(this.modalRef){
                this.closeModal();
            }
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
