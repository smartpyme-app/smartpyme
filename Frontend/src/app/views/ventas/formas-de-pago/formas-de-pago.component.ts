import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';


@Component({
    selector: 'app-formas-de-pago',
    templateUrl: './formas-de-pago.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class FormasDePagoComponent extends BaseModalComponent implements OnInit {

    public formas_pago:any = [];
    public forma_pago:any = {};
    public empresa:any = {};
    public bancos:any = [];
    public override loading:boolean = false;
    public override saving:boolean = false;
    public wompiActivo:boolean = false;

    constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.empresa = this.apiService.auth_user().empresa;
        this.loadAll();

        this.apiService.getAll('banco/cuentas/list').subscribe(bancos => { 
            this.bancos = bancos;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public loadAll() {        
        this.loading = true;
        this.apiService.getAll('formas-de-pago').subscribe(formas_pago => { 
            this.formas_pago = formas_pago;
            this.wompiActivo = formas_pago.filter((item:any) => item.nombre == 'Wompi')[0].activo;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public onSubmit(){
        this.saving = true;

        this.forma_pago.id_empresa = this.apiService.auth_user().id_empresa;

        this.apiService.store('forma-de-pago', this.forma_pago).subscribe(forma_pago => {
            this.alertService.success('Formas de pago actualizadas', 'Las formas de pago fueron actualizadas exitosamente.');
            this.saving = false;
            if (this.modalRef) {
                this.closeModal();
            }
            this.loadAll();
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public override openModal(template: TemplateRef<any>, forma_pago?: any){
        this.forma_pago = forma_pago || {};
        super.openModal(template);
    }

    public onSubmitWompi(){
        this.saving = true;
        this.apiService.store('wompi', this.empresa).subscribe(forma_pago => {
            this.saving = false;
            this.alertService.success('Conexión exitosa', 'Conexión con Wompi exitosa, ya puede crear enlaces de pago para tus ventas.');
            // this.modalRef.hide();
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('forma-de-pago/', id) .subscribe(data => {
                this.loadAll();
            }, error => {this.alertService.error(error); });
        }
    }

}
