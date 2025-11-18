import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';


@Component({
    selector: 'app-formas-de-pago',
    templateUrl: './formas-de-pago.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class FormasDePagoComponent implements OnInit {

    public formas_pago:any = [];
    public forma_pago:any = {};
    public empresa:any = {};
    public bancos:any = [];
    public loading:boolean = false;
    public saving:boolean = false;
    public wompiActivo:boolean = false;

    modalRef!: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

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

    public loadAll() {        
        this.loading = true;
        this.apiService.getAll('formas-de-pago')
            .pipe(this.untilDestroyed())
            .subscribe(formas_pago => { 
            this.formas_pago = formas_pago;
            this.wompiActivo = formas_pago.filter((item:any) => item.nombre == 'Wompi')[0].activo;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public onSubmit(){
        this.saving = true;

        this.forma_pago.id_empresa = this.apiService.auth_user().id_empresa;

        this.apiService.store('forma-de-pago', this.forma_pago)
            .pipe(this.untilDestroyed())
            .subscribe(forma_pago => {
            this.alertService.success('Formas de pago actualizadas', 'Las formas de pago fueron actualizadas exitosamente.');
            this.saving = false;
            this.modalRef.hide();
            this.loadAll();
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public openModal(template: TemplateRef<any>, forma_pago:any){
        this.forma_pago = forma_pago;
        this.modalRef = this.modalService.show(template);
    }

    public onSubmitWompi(){
        this.saving = true;
        this.apiService.store('wompi', this.empresa)
            .pipe(this.untilDestroyed())
            .subscribe(forma_pago => {
            this.saving = false;
            this.alertService.success('Conexión exitosa', 'Conexión con Wompi exitosa, ya puede crear enlaces de pago para tus ventas.');
            // this.modalRef.hide();
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('forma-de-pago/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                this.loadAll();
            }, error => {this.alertService.error(error); });
        }
    }

}
