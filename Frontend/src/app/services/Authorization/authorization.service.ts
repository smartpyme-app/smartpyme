// services/authorization.service.ts
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface Authorization {
  id: number;
  code: string;
  authorization_type: AuthorizationType;
  status: 'pending' | 'approved' | 'rejected' | 'expired';
  description: string;
  requester: any;
  authorizer?: any;
  data?: any;
  notes?: string;
  expires_at: string;
  authorized_at?: string;
  created_at: string;
}

export interface AuthorizationType {
  id: number;
  name: string;
  display_name: string;
  description?: string;
  conditions?: any;
  expiration_hours: number;
  active: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class AuthorizationService {

  constructor(private apiService: ApiService) { }

  // Verificar si una acción requiere autorización
  checkRequirement(type: string, data?: any): Observable<any> {
    return this.apiService.store('authorizations/check-requirement', { type, data });
  }

  // Solicitar autorización
  requestAuthorization(
    type: string, 
    modelType: string, 
    modelId: number | null, 
    description: string, 
    data?: any
  ): Observable<any> {
    return this.apiService.store('authorizations/request', {
      type,
      model_type: modelType,
      model_id: modelId,
      description,
      data
    });
  }

  // Obtener autorizaciones pendientes para el usuario actual
  getPendingAuthorizations(): Observable<any> {
    return this.apiService.get('authorizations/pending');
  }

  // Obtener todas las autorizaciones (con filtros)
  getAuthorizations(filters?: any): Observable<any> {
    return this.apiService.getAll('authorizations', filters);
  }

  // Obtener una autorización específica
  getAuthorization(code: string): Observable<any> {
    return this.apiService.get(`authorizations/${code}`);
  }

  // Aprobar autorización
  approveAuthorization(code: string, authorizationCode: string, notes?: string): Observable<any> {
    return this.apiService.store(`authorizations/${code}/approve`, {
      authorization_code: authorizationCode,
      notes
    });
  }

  // Rechazar autorización
  rejectAuthorization(code: string, authorizationCode: string, notes?: string): Observable<any> {
    return this.apiService.store(`authorizations/${code}/reject`, {
      authorization_code: authorizationCode,
      notes
    });
  }

  // Obtener historial de autorizaciones para un modelo
  getAuthorizationHistory(modelType: string, modelId: number): Observable<any> {
    return this.apiService.get(`authorizations/history/${encodeURIComponent(modelType)}/${modelId}`);
  }

  // Métodos para tipos de autorización (solo super admin)
  getAuthorizationTypes(filters?: any): Observable<any> {
    return this.apiService.getAll('authorization-types', filters);
  }

  createAuthorizationType(data: any): Observable<any> {
    return this.apiService.store('authorization-types', data);
  }

  getAuthorizationTypeUsers(typeId: number): Observable<any> {
    return this.apiService.get(`authorization-types/${typeId}/users`);
  }

  getAvailableUsers(typeId: number): Observable<any> {
    return this.apiService.get(`authorization-types/${typeId}/available-users`);
  }

  assignUsersToAuthorizationType(typeId: number, userIds: number[]): Observable<any> {
    return this.apiService.store(`authorization-types/${typeId}/assign-users`, {
      user_ids: userIds
    });
  }

  // Helper para formatear estados
  getStatusText(status: string): string {
    const statusMap: { [key: string]: string } = {
      'pending': 'Pendiente',
      'approved': 'Aprobada',
      'rejected': 'Rechazada',
      'expired': 'Expirada'
    };
    return statusMap[status] || status;
  }

  // Helper para obtener clase CSS según estado
  getStatusClass(status: string): string {
    const classMap: { [key: string]: string } = {
      'pending': 'badge-warning',
      'approved': 'badge-success',
      'rejected': 'badge-danger',
      'expired': 'badge-secondary'
    };
    return classMap[status] || 'badge-secondary';
  }
}