import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';
import { documentoNombreOpciones, DocumentoNombreOption } from '../documento-nombre-options';

@Component({
    selector: 'app-documento-historial',
    templateUrl: './documento-historial.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class DocumentoHistorialComponent extends BasePaginatedModalComponent implements OnInit {
    public documentos: PaginatedResponse<any> = {} as PaginatedResponse;
    public documento:any = {};
    public nombre: string = '';
    public override filtros: any = {
        paginate: 10,
        orden: 'created_at',
        direccion: 'desc'
    };
    public sucursales: any = [];

    opcionesNombreDocumento(): DocumentoNombreOption[] {
        return documentoNombreOpciones(this.apiService.auth_user()?.empresa);
    }

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        alertService: AlertService,
        apiService: ApiService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ) {
        super(apiService, alertService, modalManager);
    }

    protected override getPaginatedData(): PaginatedResponse | null {
        return this.documentos;
    }

    protected override setPaginatedData(data: PaginatedResponse): void {
        this.documentos = data;
    }

    ngOnInit() {

        console.log('nombre', this.route.snapshot.paramMap.get('nombre'));
        if (this.route.snapshot.paramMap.get('nombre')) {
            this.nombre = this.route.snapshot.paramMap.get('nombre') || '';
            this.cargarDocumentos();
        }else{
            this.router.navigate(['/documentos']);
        }
        
    }

    public cargarDocumentos() {
        this.loading = true;
        this.filtros.nombre = this.nombre;
        
        this.apiService.getAll('documentos/historial', this.filtros).pipe(this.untilDestroyed()).subscribe(
            documentos => {
                this.documentos = documentos;
                this.loading = false;
                this.cdr.markForCheck();
            },
            error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            }
        );
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = 'asc';
        }
        this.cargarDocumentos();
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    override openModal(template: TemplateRef<any>, documento:any) {
        this.documento = documento;
        console.log('documento', this.documento);

        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(sucursales => {
            this.sucursales = sucursales;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});
        
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    
}