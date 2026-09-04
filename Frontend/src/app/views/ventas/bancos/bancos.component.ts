import { Component, OnInit, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service'; 
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-bancos',
    templateUrl: './bancos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
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
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error); 
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    public async toggleBanco(banco: any): Promise<void> {
        if (this.saving) {
            return;
        }

        await super.onSubmit({
            nombre: banco.nombre,
            id_empresa: this.apiService.auth_user().id_empresa,
        }, true);

        // Recargar desde el servidor: corrige el switch si falló el guardado
        // y evita sobrescribir activo con el valor previo al toggle.
        this.loadAll();
    }

}
