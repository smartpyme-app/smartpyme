import { Component, OnInit,TemplateRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

@Component({
  selector: 'app-documento-historial',
  templateUrl: './documento-historial.component.html'
})
export class DocumentoHistorialComponent implements OnInit {
    public documentos: any = [];
    public loading: boolean = false;
    public documento:any = {};
    public nombre: string = '';
    public filtros: any = {
        paginate: 10,
        orden: 'created_at',
        direccion: 'desc'
    };
    public sucursales: any = [];

    modalRef!: BsModalRef;

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        private alertService: AlertService,
        public apiService: ApiService,
        private modalService: BsModalService
    ) {}

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
        
        this.apiService.getAll('documentos/historial', this.filtros).subscribe(
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

    public setPagination(event: any): void {
        this.loading = true;
        this.apiService.paginate(this.documentos.path + '?page=' + event.page).subscribe(
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

    public openModal(template: TemplateRef<any>, documento:any) {
        this.documento = documento;
        console.log('documento', this.documento);

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});
        
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    
}