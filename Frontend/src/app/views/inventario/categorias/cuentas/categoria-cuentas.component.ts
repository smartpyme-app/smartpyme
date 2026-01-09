import { Component, OnInit, TemplateRef, Input, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-categoria-cuentas',
    templateUrl: './categoria-cuentas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class CategoriaCuentasComponent extends BaseModalComponent implements OnInit {

    @Input() categoria:any = {};
    public cuenta:any = {};
    public sucursales:any = [];
    public catalogo:any = [];

    constructor(
        public apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe(sucursales => { 
            this.sucursales = sucursales;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('catalogo/list')
          .pipe(this.untilDestroyed())
          .subscribe(catalogo => {
            this.catalogo = catalogo;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        
    }

    override openModal(template: TemplateRef<any>, cuenta:any) {
        this.cuenta = cuenta;
        if (!this.cuenta.id) {
            this.cuenta.id_categoria = this.categoria.id;
        }
        super.openModal(template, {class: 'modal-md', backdrop: 'static'});
    }

    public onSubmit():void{
        this.saving = true;
        this.apiService.store('categoria/cuenta', this.cuenta)
          .pipe(this.untilDestroyed())
          .subscribe(cuenta => {
            if (!this.cuenta.id) {
                this.categoria.cuentas.push(cuenta);
                this.alertService.success('Cuenta creada', 'La cuenta fue añadida exitosamente.');
            }else{
                this.alertService.success('Cuenta guardada', 'La cuenta fue guardada exitosamente.');
            }
            this.saving = false;
            this.closeModal();
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck();});

    }

    public delete(cuenta:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('categoria/cuenta/', cuenta.id)
              .pipe(this.untilDestroyed())
              .subscribe(data => {
                for (let i = 0; i < this.categoria.cuentas.length; i++) { 
                    if (this.categoria.cuentas[i].id == data.id )
                        this.categoria.cuentas.splice(i, 1);
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
                   
        }

    }

}
