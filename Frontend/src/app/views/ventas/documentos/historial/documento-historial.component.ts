import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-documento-historial',
  templateUrl: './documento-historial.component.html'
})
export class DocumentoHistorialComponent implements OnInit {
    public documentos: any = [];
    public loading: boolean = false;
    public nombre: string = '';
    public filtros: any = {
        paginate: 10,
        orden: 'created_at',
        direccion: 'desc'
    };

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        private alertService: AlertService,
        public apiService: ApiService
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
}