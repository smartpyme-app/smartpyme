import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

@Component({
    selector: 'app-documento-historial',
    templateUrl: './documento-historial.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})
export class DocumentoHistorialComponent extends BasePaginatedComponent implements OnInit {
    public documentos: PaginatedResponse<any> = {} as PaginatedResponse;
    public documento:any = {};
    public nombre: string = '';
    public override filtros: any = {
        paginate: 10,
        orden: 'created_at',
        direccion: 'desc'
    };
    public sucursales: any = [];

    modalRef!: BsModalRef;

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        alertService: AlertService,
        apiService: ApiService,
        private modalService: BsModalService
    ) {
        super(apiService, alertService);
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
            },
            error => {
                this.alertService.error(error);
                this.loading = false;
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

    public openModal(template: TemplateRef<any>, documento:any) {
        this.documento = documento;
        console.log('documento', this.documento);

        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});
        
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    
}