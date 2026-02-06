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

    public async toggleBanco(nombre: string): Promise<void> {
        // Buscar el banco en la lista
        const bancoEncontrado = this.bancos.find((b: any) => b.nombre === nombre);
        if (!bancoEncontrado) {
            return;
        }
        
        // Preparar el objeto banco para guardar
        const bancoToSave = {
            nombre: bancoEncontrado.nombre,
            activo: bancoEncontrado.activo,
            id_empresa: this.apiService.auth_user().id_empresa,
            id: bancoEncontrado.id
        };
        
        // Usar el método heredado del componente base
        await super.onSubmit(bancoToSave, true);
        
        // Actualizar el banco en la lista local después de guardar
        const index = this.bancos.findIndex((b: any) => b.nombre === nombre);
        if (index !== -1) {
            this.bancos[index] = { ...this.bancos[index], ...bancoToSave };
            this.cdr.markForCheck();
        }
    }

}
