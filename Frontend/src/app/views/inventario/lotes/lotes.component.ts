import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-lotes',
  templateUrl: './lotes.component.html',
})
export class LotesComponent implements OnInit {

    public lotes: any = [];
    public loading: boolean = false;
    public downloading: boolean = false;
    public filtros: any = {};
    public bodegas: any = [];
    public productos: any = [];
    public estadisticas: any = {
        total: 0,
        vencidos: 0,
        proximos_a_vencer: 0,
        con_stock: 0,
        sin_stock: 0
    };

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private router: Router, private route: ActivatedRoute
    ) { }

    ngOnInit() {
        this.route.queryParams.subscribe(params => {
            this.filtros = {
                id_producto: +params['id_producto'] || '',
                id_bodega: +params['id_bodega'] || '',
                numero_lote: params['numero_lote'] || '',
                vencimiento_proximo: params['vencimiento_proximo'] === 'true' || false,
                vencidos: params['vencidos'] === 'true' || false,
                con_stock: params['con_stock'] === 'true' || false,
                sin_stock: params['sin_stock'] === 'true' || false,
                orden: params['orden'] || 'created_at',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            this.filtrarLotes();
        });

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
        }, error => { this.alertService.error(error); });

        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => { this.alertService.error(error); });

        this.cargarEstadisticas();
    }

    public loadAll() {
        this.filtros.id_producto = '';
        this.filtros.id_bodega = '';
        this.filtros.numero_lote = '';
        this.filtros.vencimiento_proximo = false;
        this.filtros.vencidos = false;
        this.filtros.con_stock = false;
        this.filtros.sin_stock = false;
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.filtrarLotes();
    }

    public filtrarLotes() {
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;

        // Preparar parámetros para la API
        const params: any = {
            id_producto: this.filtros.id_producto || '',
            id_bodega: this.filtros.id_bodega || '',
            numero_lote: this.filtros.numero_lote || '',
            orden: this.filtros.orden || 'created_at',
            direccion: this.filtros.direccion || 'desc',
            paginate: this.filtros.paginate || 10,
            page: this.filtros.page || 1
        };

        // Agregar filtros booleanos solo si están activos
        if (this.filtros.vencimiento_proximo) {
            params.vencimiento_proximo = true;
        }
        if (this.filtros.vencidos) {
            params.vencidos = true;
        }
        if (this.filtros.con_stock) {
            params.con_stock = true;
        }
        if (this.filtros.sin_stock) {
            params.sin_stock = true;
        }

        this.apiService.getAll('lotes', params).subscribe(lotes => {
            this.lotes = lotes;
            this.loading = false;
            if (this.modalRef) {
                this.modalRef.hide();
            }
            this.cargarEstadisticas();
        }, error => { this.alertService.error(error); this.loading = false; });
    }

    public cargarEstadisticas() {
        const params: any = {};
        if (this.filtros.id_bodega) {
            params.id_bodega = this.filtros.id_bodega;
        }
        this.apiService.getAll('lotes/estadisticas', params).subscribe(estadisticas => {
            this.estadisticas = estadisticas;
        }, error => { this.alertService.error(error); });
    }

    public filtrarPorEstadistica(tipo: string) {
        // Limpiar filtros anteriores
        this.filtros.vencimiento_proximo = false;
        this.filtros.vencidos = false;
        this.filtros.con_stock = false;
        this.filtros.sin_stock = false;
        this.filtros.page = 1;

        // Aplicar filtro según el tipo
        switch (tipo) {
            case 'vencidos':
                this.filtros.vencidos = true;
                break;
            case 'proximos_a_vencer':
                this.filtros.vencimiento_proximo = true;
                break;
            case 'con_stock':
                this.filtros.con_stock = true;
                break;
            case 'sin_stock':
                this.filtros.sin_stock = true;
                break;
            case 'todos':
                // No aplicar ningún filtro
                break;
        }

        this.filtrarLotes();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = 'asc';
        }

        this.filtrarLotes();
    }

    public setPagination(event: any): void {
        this.loading = true;
        this.filtros.page = event.page;
        this.filtrarLotes();
    }

    public openFilter(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public descargar() {
        this.downloading = true;
        // TODO: Implementar exportación de lotes si es necesario
        this.alertService.info('Funcionalidad en desarrollo', 'La exportación de lotes estará disponible próximamente.');
        this.downloading = false;
    }

    public delete(id: number) {
        if (confirm('¿Desea eliminar el lote? Esta acción no se puede deshacer.')) {
            this.loading = true;
            this.apiService.delete('lotes/', id).subscribe(data => {
                this.alertService.success('Lote eliminado', 'El lote fue eliminado exitosamente.');
                this.filtrarLotes();
            }, error => { this.alertService.error(error); this.loading = false; });
        }
    }

    public getEstadoVencimiento(lote: any): string {
        if (!lote.fecha_vencimiento) {
            return 'sin_vencimiento';
        }

        const hoy = new Date();
        const vencimiento = new Date(lote.fecha_vencimiento);
        const diasRestantes = Math.ceil((vencimiento.getTime() - hoy.getTime()) / (1000 * 60 * 60 * 24));

        if (diasRestantes < 0) {
            return 'vencido';
        } else if (diasRestantes === 0) {
            return 'venciendo_hoy';
        } else if (diasRestantes <= 7) {
            return 'venciendo_proximo';
        } else {
            return 'vigente';
        }
    }

    public getClaseEstado(estado: string): string {
        switch (estado) {
            case 'vencido':
                return 'badge bg-danger';
            case 'venciendo_hoy':
                return 'badge bg-warning';
            case 'venciendo_proximo':
                return 'badge bg-info';
            case 'vigente':
                return 'badge bg-success';
            default:
                return 'badge bg-secondary';
        }
    }

    public getTextoEstado(estado: string): string {
        switch (estado) {
            case 'vencido':
                return 'Vencido';
            case 'venciendo_hoy':
                return 'Vence hoy';
            case 'venciendo_proximo':
                return 'Vence pronto';
            case 'vigente':
                return 'Vigente';
            default:
                return 'Sin vencimiento';
        }
    }

    public getDiasVencimiento(fechaVencimiento: string): string {
        if (!fechaVencimiento) return '';
        
        const hoy = new Date();
        const vencimiento = new Date(fechaVencimiento);
        const diasRestantes = Math.ceil((vencimiento.getTime() - hoy.getTime()) / (1000 * 60 * 60 * 24));
        
        if (diasRestantes < 0) {
            return `Vencido hace ${Math.abs(diasRestantes)} día(s)`;
        } else if (diasRestantes === 0) {
            return 'Vence hoy';
        } else if (diasRestantes === 1) {
            return 'Vence mañana';
        } else {
            return `${diasRestantes} días`;
        }
    }

    public getClaseVencimiento(fechaVencimiento: string): string {
        if (!fechaVencimiento) return '';
        
        const hoy = new Date();
        const vencimiento = new Date(fechaVencimiento);
        const diasRestantes = Math.ceil((vencimiento.getTime() - hoy.getTime()) / (1000 * 60 * 60 * 24));
        
        if (diasRestantes < 0) {
            return 'text-danger';
        } else if (diasRestantes === 0) {
            return 'text-warning';
        } else if (diasRestantes <= 7) {
            return 'text-info';
        } else {
            return 'text-success';
        }
    }

    public isLotesActivo(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        
        // Si custom_empresa es string, parsearlo
        const customConfig = typeof empresa.custom_empresa === 'string' 
            ? JSON.parse(empresa.custom_empresa) 
            : empresa.custom_empresa;
        
        return customConfig?.configuraciones?.lotes_activo === true;
    }

}
