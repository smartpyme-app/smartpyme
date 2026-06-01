import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface DteDocument {
  id: number;
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
  email_message_id?: string;
  json_path?: string;
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

  updateDestino(id: number, destino: 'compra' | 'gasto'): Observable<{ success: boolean; document: DteDocument }> {
    return this.api.patch(`dtes`, id, { destino });
  }

  procesar(id: number): Observable<{ success: boolean; message?: string; document?: DteDocument; compra_id?: number; gasto_id?: number }> {
    return this.api.store(`dtes/${id}/procesar`, {});
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
