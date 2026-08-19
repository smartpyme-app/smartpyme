import { Component, EventEmitter, Input, OnInit, Output, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { FE_PAIS_HN, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

@Component({
    selector: 'app-recalcular-precios-tc',
    standalone: true,
    imports: [CommonModule, FormsModule, TooltipModule],
    templateUrl: './recalcular-precios-tc.component.html',
})
export class RecalcularPreciosTcComponent implements OnInit {
    @Input() contexto: 'productos' | 'servicios' = 'productos';
    @Output() recalculado = new EventEmitter<void>();
    @ViewChild('modalTc') modalTemplate!: TemplateRef<unknown>;

    visible = false;
    loading = false;
    exchangeRate: number | null = null;
    rateApi: number | null = null;
    rateCatalogo: number | null = null;
    tieneCatalogo = false;
    date: string | null = null;
    aplicarProductos = true;
    aplicarServicios = false;
    modalRef?: BsModalRef;

    constructor(
        public apiService: ApiService,
        private alertService: AlertService,
        private modalService: BsModalService,
        private funcionalidadesService: FuncionalidadesService,
    ) {}

    ngOnInit(): void {
        const empresa = this.apiService.auth_user()?.empresa;
        const esHn = resolveCodigoPaisFe(empresa) === FE_PAIS_HN;
        if (!esHn || !this.apiService.canEdit()) {
            return;
        }
        this.funcionalidadesService.verificarAcceso('multimoneda').subscribe((ok) => {
            this.visible = !!ok;
        });
    }

    usarSugerido(): void {
        if (this.rateApi != null) {
            this.exchangeRate = this.rateApi;
        }
    }

    openModal(): void {
        this.aplicarProductos = this.contexto === 'productos';
        this.aplicarServicios = this.contexto === 'servicios';
        this.loading = true;
        this.apiService.getAll('productos/tipo-cambio-precios').subscribe({
            next: (res: any) => {
                this.exchangeRate = res?.rate != null ? parseFloat(res.rate) : null;
                this.rateApi = res?.rate_api != null ? parseFloat(res.rate_api) : null;
                this.rateCatalogo = res?.rate_catalogo != null ? parseFloat(res.rate_catalogo) : null;
                this.tieneCatalogo = !!res?.tiene_catalogo;
                this.date = res?.date ?? null;
                this.loading = false;
                this.modalRef = this.modalService.show(this.modalTemplate, { class: 'modal-md', backdrop: 'static' });
            },
            error: (err: any) => {
                this.loading = false;
                this.alertService.error(err);
            },
        });
    }

    guardarTc(): void {
        const rate = parseFloat(String(this.exchangeRate));
        if (!(rate > 0)) {
            this.alertService.warning('Tipo de cambio', 'Ingrese un tipo de cambio mayor que cero.');
            return;
        }
        this.loading = true;
        const eraPrimera = !this.tieneCatalogo;
        this.apiService.putToUrl('productos/tipo-cambio-precios', { exchange_rate: rate }).subscribe({
            next: (res: any) => {
                this.aplicarSnapshot(res);
                this.loading = false;
                this.alertService.success(
                    'Tipo de cambio',
                    eraPrimera
                        ? 'Base guardada. Los precios no cambiaron. Ya puede recalcular cuando el TC sea otro.'
                        : 'Se guardó el tipo de cambio para ventas. Los precios del catálogo no se modificaron.',
                );
            },
            error: (err: any) => {
                this.loading = false;
                this.alertService.error(err);
            },
        });
    }

    recalcular(): void {
        const rate = parseFloat(String(this.exchangeRate));
        if (!(rate > 0)) {
            this.alertService.warning('Tipo de cambio', 'Ingrese un tipo de cambio mayor que cero.');
            return;
        }
        if (!this.tieneCatalogo) {
            this.alertService.warning('Tipo de cambio', 'Primero guarde el tipo de cambio inicial.');
            return;
        }
        if (!this.aplicarProductos && !this.aplicarServicios) {
            this.alertService.warning('Recalcular', 'Seleccione productos y/o servicios.');
            return;
        }
        if (!confirm('Se actualizarán los precios de venta del catálogo según el nuevo tipo de cambio. ¿Continuar?')) {
            return;
        }
        this.loading = true;
        this.apiService.store('productos/tipo-cambio-precios/recalcular', {
            exchange_rate: rate,
            aplicar_productos: this.aplicarProductos,
            aplicar_servicios: this.aplicarServicios,
        }).subscribe({
            next: (res: any) => {
                this.aplicarSnapshot(res);
                this.loading = false;
                this.alertService.success(
                    'Precios actualizados',
                    `Se recalcularon ${res?.actualizados ?? 0} registros.`,
                );
                this.modalRef?.hide();
                this.recalculado.emit();
            },
            error: (err: any) => {
                this.loading = false;
                this.alertService.error(err);
            },
        });
    }

    private aplicarSnapshot(res: any): void {
        this.exchangeRate = res?.rate != null ? parseFloat(res.rate) : this.exchangeRate;
        this.rateApi = res?.rate_api != null ? parseFloat(res.rate_api) : this.rateApi;
        this.rateCatalogo = res?.rate_catalogo != null ? parseFloat(res.rate_catalogo) : this.rateCatalogo;
        this.tieneCatalogo = !!res?.tiene_catalogo;
        this.date = res?.date ?? this.date;
    }
}
