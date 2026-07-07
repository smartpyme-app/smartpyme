import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';
import { FeCrErrorEmisionPayload } from '@services/facturacion-electronica/fe-cr-http-error.util';
import { abrirVentanaTextoFeCr } from '@services/facturacion-electronica/fe-cr-abrir-xml.util';

@Component({
  selector: 'app-fe-cr-emision-avanzado',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  template: `
    @if (intento?.xml_comprobante || intento?.documento) {
      <details class="small text-muted mb-3 text-start">
        <summary class="cursor-pointer user-select-none py-1">
          {{ 'country.tax.fe.emitFailedAdvancedOptions' | translate }}
        </summary>
        <div class="mt-2 ps-1">
          @if (intento?.xml_comprobante) {
            <button
              type="button"
              (click)="abrirXml()"
              class="mb-2 btn btn-outline-secondary btn-sm w-100"
            >
              {{ 'country.tax.fe.emitFailedViewAttemptXml' | translate }}
            </button>
          }
          @if (intento?.documento) {
            <button
              type="button"
              (click)="abrirJson()"
              class="btn btn-outline-secondary btn-sm w-100"
            >
              {{ 'country.tax.fe.emitFailedViewAttemptJson' | translate }}
            </button>
          }
        </div>
      </details>
    }
  `,
})
export class FeCrEmisionAvanzadoComponent {
  @Input() intento?: FeCrErrorEmisionPayload | null;

  abrirXml(): void {
    const xml = this.intento?.xml_comprobante;
    if (typeof xml === 'string' && xml.length > 0) {
      abrirVentanaTextoFeCr(xml, 'application/xml', 'XML comprobante CR');
    }
  }

  abrirJson(): void {
    const doc = this.intento?.documento;
    if (doc != null) {
      abrirVentanaTextoFeCr(
        JSON.stringify(doc, null, 2),
        'application/json',
        'JSON payload FE CR'
      );
    }
  }
}
