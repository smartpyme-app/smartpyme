import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface EmailAccount {
  id: number;
  email: string;
  provider: string;
  is_active: boolean;
  last_sync_at?: string;
  id_sucursal?: number;
  id_bodega?: number;
  actualizar_inventario?: boolean;
  notification_user_id?: number | null;
  notification_user?: { id: number; name: string; email?: string } | null;
  sucursal?: { id: number; nombre: string };
  bodega?: { id: number; nombre: string };
}

@Injectable({
  providedIn: 'root'
})
export class EmailAccountService {
  constructor(private api: ApiService) {}

  list(): Observable<EmailAccount[]> {
    return this.api.getAll('email-accounts');
  }

  testImap(config: { host: string; port: number; encryption: string; user: string; password: string }): Observable<{ success: boolean; message: string }> {
    return this.api.store('email-accounts/imap/test', config);
  }

  storeImap(config: any): Observable<{ success: boolean; message: string; account: EmailAccount }> {
    return this.api.store('email-accounts/imap', config);
  }

  sync(
    id: number,
    options?: { dias?: number; fecha_inicio?: string; fecha_fin?: string }
  ): Observable<{ success: boolean; message: string; date_from: string; date_to: string; last_sync_at?: string }> {
    let body: { dias?: number; fecha_inicio?: string; fecha_fin?: string } = {};
    if (options?.fecha_inicio && options?.fecha_fin) {
      body = { fecha_inicio: options.fecha_inicio, fecha_fin: options.fecha_fin };
    } else if (options?.dias) {
      body = { dias: options.dias };
    }
    return this.api.store(`email-accounts/${id}/sync`, body);
  }

  destroy(id: number): Observable<{ success: boolean; message: string }> {
    return this.api.delete('email-accounts/', id);
  }

  getGmailAuthUrl(): Observable<{ url: string }> {
    return this.api.getAll('email-accounts/gmail/redirect');
  }

  updateNotificaciones(id: number, notificationUserId: number | null): Observable<{ success: boolean; message: string; account: EmailAccount }> {
    return this.api.store(`email-accounts/${id}/notificaciones`, { notification_user_id: notificationUserId });
  }
}
