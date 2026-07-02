import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface DteLineItem {
  numero: number;
  codigo?: string;
  descripcion: string;
  cantidad: number;
  precio_unitario: number;
  total: number;
}

export interface DteDocument {
  id: number;
  pais?: string;
  formato_origen?: string;
  dte_uuid: string;
  dte_type: string;
  dte_number: string;
  emission_date: string;
  total_amount: number;
  issuer_nit: string;
  issuer_name: string;
  receiver_nit?: string;
  validation_status: string;
  validation_errors?: string[];
  processing_status: string;
  processing_errors?: string;
  destino?: string;
  id_proyecto?: number | null;
  id_categoria?: number | null;
  tipo_gasto?: string | null;
  tipo_costo_gasto?: string | null;
  line_items?: DteLineItem[];
  email_message_id?: string;
  json_path?: string;
  xml_path?: string;
  acuse_xml_path?: string;
  acuse_estado?: string | null;
  pdf_path?: string;
  user_email_account?: { id: number; email: string; provider: string };
}

export interface DteDocumentsResponse {
  data: DteDocument[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface DteProcesarPayload {
  destino?: 'compra' | 'gasto';
  id_proyecto?: number | null;
  id_categoria?: number | null;
  tipo_gasto?: string | null;
  tipo_costo_gasto?: string | null;
}

@Injectable({
  providedIn: 'root'
})
export class DteDocumentService {
  constructor(private api: ApiService) {}

  list(filtros: any = {}): Observable<DteDocumentsResponse> {
    return this.api.getAll('dtes', filtros);
  }

  get(id: number): Observable<DteDocument> {
    return this.api.read('dtes/', id);
  }

  downloadJson(id: number): Observable<Blob> {
    return this.api.download(`dtes/${id}/download/json`);
  }

  downloadPdf(id: number): Observable<Blob> {
    return this.api.download(`dtes/${id}/download/pdf`);
  }

  downloadXml(id: number): Observable<Blob> {
    return this.api.download(`dtes/${id}/download/xml`);
  }

  downloadAcuse(id: number): Observable<Blob> {
    return this.api.download(`dtes/${id}/download/acuse`);
  }

  updateDestino(id: number, destino: 'compra' | 'gasto'): Observable<{ success: boolean; document: DteDocument }> {
    return this.update(id, { destino });
  }

  update(
    id: number,
    payload: Partial<Pick<DteDocument, 'destino' | 'id_proyecto' | 'id_categoria' | 'tipo_gasto' | 'tipo_costo_gasto'>>
  ): Observable<{ success: boolean; document: DteDocument }> {
    // ponytail: PUT evita PATCH bloqueado por CORS en producción (nginx/ALB)
    return this.api.update(`dtes`, id, payload);
  }

  procesar(
    id: number,
    payload: DteProcesarPayload = {}
  ): Observable<{ success: boolean; message?: string; document?: DteDocument; compra_id?: number; gasto_id?: number }> {
    return this.api.storeWithTimeout(`dtes/${id}/procesar`, payload, 300000);
  }

  anular(id: number): Observable<{ success: boolean; message: string; document: DteDocument }> {
    return this.api.store(`dtes/${id}/anular`, {});
  }

  getPendingReviewAlert(): Observable<{ show_alert: boolean; pending_count: number }> {
    return this.api.getAll('dtes/pending-review-alert');
  }

  /** Evita mostrar la alerta de revisión durante la sesión y el día actual. */
  dismissReviewAlert(userId: number): void {
    const today = new Date().toISOString().slice(0, 10);
    sessionStorage.setItem(`dte_review_dismiss_session_${userId}`, '1');
    localStorage.setItem(`dte_review_dismiss_day_${userId}`, today);
  }

  isReviewAlertDismissed(userId: number): boolean {
    if (sessionStorage.getItem(`dte_review_dismiss_session_${userId}`)) {
      return true;
    }
    const today = new Date().toISOString().slice(0, 10);
    return localStorage.getItem(`dte_review_dismiss_day_${userId}`) === today;
  }
}
