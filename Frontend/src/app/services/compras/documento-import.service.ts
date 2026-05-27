import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';
import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

export interface DocumentoImportResponse {
  pais: string;
  formato_origen: 'json' | 'xml';
  tipo_documento_nombre: string;
  documento: Record<string, unknown>;
  dte: {
    identificacion?: Record<string, unknown>;
    emisor?: Record<string, unknown>;
    cuerpoDocumento?: unknown[];
    resumen?: Record<string, unknown>;
    selloRecibido?: string;
  };
  gasto?: Record<string, unknown>;
  mensaje?: string;
  error?: string;
}

/** Textos de UI compartidos (sin mencionar formato de archivo). */
export const DOCUMENTO_IMPORT_UI = {
  tituloCompra: 'Importar compra desde documento electrónico',
  tituloGasto: 'Importar gasto desde documento electrónico',
  boton: 'Importar documento',
  ayudaModal:
    'Importe un solo comprobante del proveedor por vez: seleccione un archivo o pegue el contenido en el cuadro de texto.',
  placeholder: 'Contenido del comprobante recibido del proveedor',
  sinDatos: 'Seleccione un archivo o pegue el contenido del comprobante.',
} as const;

/**
 * Importación de documentos electrónicos recibidos vía API.
 */
@Injectable({ providedIn: 'root' })
export class DocumentoImportService {
  readonly ui = DOCUMENTO_IMPORT_UI;

  constructor(private apiService: ApiService) {}

  /** Filtro del selector de archivos (sin mostrarlo al usuario en etiquetas). */
  extensionesAceptadas(): string {
    const cr = resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR;
    return cr ? '.xml,.json,.txt' : '.json,.txt';
  }

  importarCompra(contenido: string): Observable<DocumentoImportResponse> {
    return this.apiService.store('compras/importar-documento', { contenido });
  }

  importarGasto(contenido: string): Observable<DocumentoImportResponse> {
    return this.apiService.store('gastos/importar-documento', { contenido });
  }

  /**
   * Interpreta el documento según contexto (compra o gasto).
   */
  importar(
    contenido: string,
    contexto: 'compra' | 'gasto'
  ): Observable<DocumentoImportResponse> {
    return contexto === 'gasto'
      ? this.importarGasto(contenido)
      : this.importarCompra(contenido);
  }
}
