import { Component, OnInit, Input, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-cliente-documentos',
    templateUrl: './cliente-documentos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ClienteDocumentosComponent extends BaseCrudComponent<any> implements OnInit {
    public documento: any = {};
    public documentos: any = [];

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private route: ActivatedRoute,
        private router: Router,
        private cdr: ChangeDetectorRef
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'cliente/documento',
            itemsProperty: 'documentos',
            itemProperty: 'documento',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El documento fue guardado exitosamente.',
                updated: 'El documento fue guardado exitosamente.',
                deleted: 'El documento fue eliminado exitosamente.',
                createTitle: 'Documento guardado',
                updateTitle: 'Documento guardado',
                deleteTitle: 'Documento eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            },
            beforeSave: (item) => {
                item.cliente_id = this.route.snapshot.paramMap.get('id');
                return item;
            },
            afterSave: (item, isNew) => {
                if (isNew) {
                    this.documentos.push(item);
                }
                this.documento = {};
            },
            afterDelete: () => {
                // La lista se actualiza manualmente en delete
            }
        });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll(){
        this.loading = true;
        const clienteId = this.route.snapshot.paramMap.get('id')!;
        this.apiService.getAll(`cliente/${clienteId}/documentos`)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (documentos) => {
                    this.documentos = documentos;
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            });
    }

    public updateNombre(documento: any) {
        this.onSubmit(documento);
    }

    public setFile(event: any) {
        this.documento.file = event.target.files[0];
        this.documento.cliente_id = this.route.snapshot.paramMap.get('id')!;
        
        let formData: FormData = new FormData();
        for (var key in this.documento) {
            formData.append(key, this.documento[key]);
        }

        this.loading = true;
        this.apiService.store('cliente/documento', formData)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (documento) => {
                    if(!this.documento.id) {
                        this.documentos.push(documento);
                    }
                    this.documento = {};
                    this.loading = false;
                    this.alertService.success('Documento guardado', 'El documento fue guardado exitosamente');
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.documento = {};
                    this.cdr.markForCheck();
                }
            });
    }

    public override delete(documento: any){
        super.delete(documento.id || documento);
    }

    public verDocumento(documento:any){
        var ventana = window.open(this.apiService.baseUrl + "/img/" + documento.url + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }


}
