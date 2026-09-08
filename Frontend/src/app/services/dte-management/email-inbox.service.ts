import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface EmailInbox {
  id: number;
  email: string;
  token: string;
  status: 'ACTIVE' | 'PAUSED' | 'DISABLED';
  last_email_at?: string | null;
  last_dte_at?: string | null;
  emails_received: number;
  dtes_imported: number;
  verification_code?: string | null;
  verification_link?: string | null;
  verification_received_at?: string | null;
}

@Injectable({
  providedIn: 'root'
})
export class EmailInboxService {
  constructor(private api: ApiService) {}

  show(): Observable<{ inbox: EmailInbox | null }> {
    return this.api.getAll('email-inboxes');
  }

  activate(): Observable<{ success: boolean; message: string; inbox: EmailInbox }> {
    return this.api.store('email-inboxes', {});
  }

  pause(id: number): Observable<{ success: boolean; inbox: EmailInbox }> {
    return this.api.store(`email-inboxes/${id}/pause`, {});
  }

  resume(id: number): Observable<{ success: boolean; inbox: EmailInbox }> {
    return this.api.store(`email-inboxes/${id}/resume`, {});
  }

  regenerate(id: number): Observable<{ success: boolean; message: string; inbox: EmailInbox }> {
    return this.api.store(`email-inboxes/${id}/regenerate`, {});
  }

  disable(id: number): Observable<{ success: boolean; message: string }> {
    return this.api.delete('email-inboxes/', id);
  }
}
