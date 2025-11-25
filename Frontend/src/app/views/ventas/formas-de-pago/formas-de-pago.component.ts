import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-formas-de-pago',
    templateUrl: './formas-de-pago.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class FormasDePagoComponent extends BaseCrudComponent<any> implements OnInit {

    public formas_pago:any = [];
    public forma_pago:any = {};
    public empresa:any = {};
    public bancos:any = [];
    public wompiActivo:boolean = false;

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'forma-de-pago',
            itemsProperty: 'formas_pago',
            itemProperty: 'forma_pago',
            messages: {
                created: 'Las formas de pago fueron actualizadas exitosamente.',
                updated: 'Las formas de pago fueron actualizadas exitosamente.',
                createTitle: 'Formas de pago actualizadas',
                updateTitle: 'Formas de pago actualizadas'
            },
            beforeSave: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                return item;
            }
        });
    }

    ngOnInit() {
        this.empresa = this.apiService.auth_user().empresa;
        this.loadAll();

        this.apiService.getAll('banco/cuentas/list')
            .pipe(this.untilDestroyed())
            .subscribe(bancos => { 
                this.bancos = bancos;
                this.loading = false;
            }, error => {this.alertService.error(error); });
    }

    public override loadAll() {
        this.loading = true;
        this.apiService.getAll('formas-de-pago')
            .pipe(this.untilDestroyed())
            .subscribe(formas_pago => { 
            this.formas_pago = formas_pago;
            this.wompiActivo = formas_pago.filter((item:any) => item.nombre == 'Wompi')[0]?.activo || false;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    public override openModal(template: TemplateRef<any>, forma_pago?: any){
        super.openModal(template, forma_pago);
    }

    public onSubmitWompi(){
        this.saving = true;
        this.apiService.store('wompi', this.empresa)
            .pipe(this.untilDestroyed())
            .subscribe(forma_pago => {
            this.saving = false;
            this.alertService.success('Conexión exitosa', 'Conexión con Wompi exitosa, ya puede crear enlaces de pago para tus ventas.');
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
