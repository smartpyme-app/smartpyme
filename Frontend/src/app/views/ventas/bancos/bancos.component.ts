import { Component, OnInit, TemplateRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service'; 
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { NgSelectModule } from '@ng-select/ng-select';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-bancos',
    templateUrl: './bancos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ReactiveFormsModule, NgSelectModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class BancosComponent extends BaseCrudComponent<any> implements OnInit {

    public bancos:any = [];
    public banco:any = {};
    private cdr = inject(ChangeDetectorRef);

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'banco',
            itemsProperty: 'bancos',
            itemProperty: 'banco',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'Los bancos fueron actualizadas exitosamente.',
                updated: 'Los bancos fueron actualizadas exitosamente.',
                createTitle: 'Bancos actualizadas',
                updateTitle: 'Bancos actualizadas'
            },
            beforeSave: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                return item;
            },
            afterSave: () => {
                // El modal se cierra automáticamente por el componente base
            }
        });
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.loading = true;
        this.apiService.getAll('bancos')
            .pipe(this.untilDestroyed())
            .subscribe(bancos => { 
                this.bancos = bancos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    public onSubmit(nombre:any){
        // Método personalizado que recibe nombre como parámetro
        this.banco.nombre = nombre;
        this.banco.id_empresa = this.apiService.auth_user().id_empresa;
        // Usar el método heredado
        this.onSubmit(this.banco);
    }

}
