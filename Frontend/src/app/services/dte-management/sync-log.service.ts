import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface SyncLogFailure {
  email_message_id?: string;
  error: string;
}

export interface SyncLog {
  id: number;
  user_email_account_id: number;
  started_at: string;
  finished_at?: string;
  status: string;
  emails_scanned: number;
  dtes_found: number;
  dtes_processed: number;
  dtes_duplicates?: number;
  dtes_failed: number;
  error_message?: string;
  failure_details?: SyncLogFailure[];
  user_email_account?: { id: number; email: string; provider: string };
}

export interface SyncLogsResponse {
  data: SyncLog[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

@Injectable({
  providedIn: 'root'
})
export class SyncLogService {
  constructor(private api: ApiService) {}

  list(filtros: any = {}): Observable<SyncLogsResponse> {
    return this.api.getAll('sync-logs', filtros);
  }
}
