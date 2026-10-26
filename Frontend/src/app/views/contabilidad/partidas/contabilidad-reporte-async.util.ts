import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { descargarBlob } from '../libro-iva-shared/libro-iva-descarga.util';
import { Observable, Subscription } from 'rxjs';

/** Mismo criterio que backend ContabilidadReporteRango::PDF_MAX_DAYS */
export const CONTABILIDAD_PDF_MAX_DIAS = 31;

export function diasEnRangoContabilidad(fechaInicio: string, fechaFin: string): number {
  const start = new Date(fechaInicio + 'T00:00:00');
  const end = new Date(fechaFin + 'T00:00:00');
  const diff = Math.round((end.getTime() - start.getTime()) / 86400000);
  return diff + 1;
}

export function mensajePdfRangoLargo(): string {
  return `El PDF solo está disponible para periodos de hasta ${CONTABILIDAD_PDF_MAX_DIAS} días. Para rangos más largos, seleccione Excel.`;
}

export function validarFormatoPdfContabilidad(
  formato: string,
  fechaInicio: string,
  fechaFin: string,
  soloPdf = false
): string | null {
  if (formato !== 'pdf') {
    return null;
  }
  if (diasEnRangoContabilidad(fechaInicio, fechaFin) <= CONTABILIDAD_PDF_MAX_DIAS) {
    return null;
  }
  if (soloPdf) {
    return `El PDF solo está disponible para periodos de hasta ${CONTABILIDAD_PDF_MAX_DIAS} días. Acorte el rango de fechas.`;
  }
  return mensajePdfRangoLargo();
}

export interface EncolarContabilidadBody {
  tipo_reporte: string;
  formato: string;
  fecha_inicio: string;
  fecha_fin: string;
  parametros?: Record<string, unknown>;
}

export function encolarYDescargarReporteContabilidad(
  api: ApiService,
  alert: AlertService,
  body: EncolarContabilidadBody,
  pipeDestroy: <T>(source: Observable<T>) => Observable<T>,
  onBusy: (busy: boolean) => void,
  nombreFallback: string
): Subscription {
  onBusy(true);

  return pipeDestroy(api.encolarReporteExportacion('reportes/contabilidad/exportaciones', body)).subscribe({
    next: (response) => {
      alert.info(
        'Generando reporte',
        'El reporte se está generando en segundo plano. La descarga iniciará automáticamente.'
      );
      pollExportacionContabilidad(
        api,
        alert,
        response.id,
        pipeDestroy,
        onBusy,
        nombreFallback,
        body.formato
      );
    },
    error: (error) => {
      const msg =
        error?.error?.errors?.formato?.[0] ||
        error?.error?.message ||
        error?.error?.error ||
        'Error al encolar el reporte.';
      alert.error(msg);
      onBusy(false);
    },
  });
}

function pollExportacionContabilidad(
  api: ApiService,
  alert: AlertService,
  exportacionId: number,
  pipeDestroy: <T>(source: Observable<T>) => Observable<T>,
  onBusy: (busy: boolean) => void,
  nombreFallback: string,
  formato: string,
  intento = 0
): void {
  const maxIntentos = 600;
  const intervaloMs = 3000;

  pipeDestroy(api.estadoReporteExportacion(exportacionId)).subscribe({
    next: (estado) => {
      if (estado.estado === 'done') {
        pipeDestroy(api.descargarReporteExportacion(exportacionId)).subscribe({
          next: (blob) => {
            if (!blob?.size) {
              alert.error('El archivo generado está vacío');
              onBusy(false);
              return;
            }
            const nombre = estado.nombre_archivo || nombreFallback;
            const ext = nombre.split('.').pop() || (formato === 'pdf' ? 'pdf' : 'xlsx');
            const mime =
              ext === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            descargarBlob(blob, mime, nombre);
            alert.success('Descarga completada', 'El reporte se ha descargado correctamente.');
            onBusy(false);
          },
          error: () => {
            alert.error('Error al descargar el reporte.');
            onBusy(false);
          },
        });
        return;
      }

      if (estado.estado === 'failed') {
        alert.error(estado.error || 'Error al generar el reporte.');
        onBusy(false);
        return;
      }

      if (intento >= maxIntentos) {
        alert.error('El reporte está tardando demasiado. Intente más tarde.');
        onBusy(false);
        return;
      }

      setTimeout(
        () =>
          pollExportacionContabilidad(
            api,
            alert,
            exportacionId,
            pipeDestroy,
            onBusy,
            nombreFallback,
            formato,
            intento + 1
          ),
        intervaloMs
      );
    },
    error: () => {
      alert.error('Error al consultar el estado del reporte.');
      onBusy(false);
    },
  });
}
