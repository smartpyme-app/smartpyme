import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { BaseComponent } from '@shared/base/base.component';
import { ActivosNavTabsComponent } from '@views/contabilidad/activos/activos-nav-tabs/activos-nav-tabs.component';
import { descargarBlob } from '@views/contabilidad/libro-iva-shared/libro-iva-descarga.util';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-activos-depreciacion',
    templateUrl: './depreciacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, CurrencyPipe, ActivosNavTabsComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActivosDepreciacionComponent extends BaseComponent implements OnInit {

    public periodo = '';
    public preview: any = null;
    public loading = false;
    public ejecutando = false;
    public importando = false;
    public descargandoPlantilla = false;
    public archivoImport: File | null = null;
    public resultadoImport: { creados: number; omitidos: number; errores: { fila: number; mensaje: string }[] } | null = null;

    constructor(
        public apiService: ApiService,
        protected alertService: AlertService,
        private cdr: ChangeDetectorRef,
    ) {
        super();
    }

    ngOnInit() {
        const now = new Date();
        this.periodo = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        this.cargarPreview();
    }

    cargarPreview() {
        if (!this.periodo) {
            return;
        }
        this.loading = true;
        this.apiService.getAll('activos/depreciacion/preview', { periodo: this.periodo })
            .pipe(this.untilDestroyed())
            .subscribe(preview => {
                this.preview = preview;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    ejecutar() {
        if (!this.preview?.cantidad || this.preview?.ya_aplicada) {
            return;
        }

        const totalFmt = this.preview.total;
        const periodo = this.periodo;
        const partidaDisponible = !!this.preview.partida_contable_disponible;
        const partidaMotivo = this.preview.partida_contable_motivo ?? '';

        Swal.fire({
            title: '¿Aplicar depreciación?',
            html: `
                <p class="mb-2">Período <strong>${periodo}</strong> — ${this.preview.cantidad} activo(s) — Total: <strong>${totalFmt}</strong></p>
                <div class="form-check text-start">
                    <input class="form-check-input" type="checkbox" id="swal-generar-partida" ${partidaDisponible ? 'checked' : 'disabled'}>
                    <label class="form-check-label" for="swal-generar-partida">Generar partida contable del egreso</label>
                </div>
                ${!partidaDisponible && partidaMotivo ? `<p class="text-muted small text-start mt-2 mb-0">${partidaMotivo}</p>` : ''}
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ejecutar corrida',
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            preConfirm: () => {
                const el = document.getElementById('swal-generar-partida') as HTMLInputElement | null;
                return { generarPartida: !!(el && el.checked && partidaDisponible) };
            },
        }).then(result => {
            if (!result.isConfirmed || !result.value) {
                return;
            }
            this.ejecutando = true;
            this.cdr.markForCheck();
            this.apiService.store('activos/depreciacion/ejecutar', {
                periodo,
                generar_partida: result.value.generarPartida,
            })
                .pipe(this.untilDestroyed())
                .subscribe(res => {
                    let msg = `Egreso #${res.id_egreso} creado.`;
                    if (result.value.generarPartida) {
                        if (res.partida_generada) {
                            msg += ` Partida #${res.partida_id} generada.`;
                        } else if (res.partida_error) {
                            this.alertService.warning('Corrida aplicada', `${msg} No se generó la partida: ${res.partida_error}`);
                            this.ejecutando = false;
                            this.cargarPreview();
                            return;
                        }
                    }
                    this.alertService.success('Corrida aplicada', msg);
                    this.ejecutando = false;
                    this.cargarPreview();
                }, error => {
                    this.alertService.error(error);
                    this.ejecutando = false;
                    this.cdr.markForCheck();
                });
        });
    }

    descargarPlantilla() {
        this.descargandoPlantilla = true;
        this.apiService.export('activos/import/plantilla', {})
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (data: Blob) => {
                    descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'plantilla_activos_fijos.xlsx');
                    this.descargandoPlantilla = false;
                    this.cdr.markForCheck();
                },
                error: error => {
                    this.alertService.error(error);
                    this.descargandoPlantilla = false;
                    this.cdr.markForCheck();
                },
            });
    }

    onArchivoImport(event: Event) {
        const input = event.target as HTMLInputElement;
        this.archivoImport = input.files?.[0] ?? null;
        this.resultadoImport = null;
        this.cdr.markForCheck();
    }

    importarActivos() {
        if (!this.archivoImport) {
            this.alertService.warning('Importación', 'Seleccione un archivo Excel.');
            return;
        }
        const formData = new FormData();
        formData.append('file', this.archivoImport);
        this.importando = true;
        this.resultadoImport = null;
        this.apiService.upload('activos/import', formData)
            .pipe(this.untilDestroyed())
            .subscribe(res => {
                this.resultadoImport = res;
                this.importando = false;
                const errores = res.errores?.length ?? 0;
                if (errores === 0) {
                    this.alertService.success('Importación', `${res.creados} activo(s) creado(s). ${res.omitidos ? res.omitidos + ' omitido(s).' : ''}`);
                } else {
                    this.alertService.warning('Importación con observaciones', `${res.creados} creado(s), ${errores} fila(s) con error.`);
                }
                this.archivoImport = null;
                this.cargarPreview();
            }, error => {
                this.alertService.error(error);
                this.importando = false;
                this.cdr.markForCheck();
            });
    }
}
