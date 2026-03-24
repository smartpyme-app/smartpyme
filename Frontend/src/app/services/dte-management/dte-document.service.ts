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
}
