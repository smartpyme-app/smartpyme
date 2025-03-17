// src/app/services/constants.service.ts

import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { environment } from 'src/environments/environment';
import { tap } from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class ConstantsService {
  public appUrl: string = environment.APP_URL;
  public baseUrl: string = environment.API_URL;
  public apiUrl = this.baseUrl + '/api/';
  private constantsSubject = new BehaviorSubject<any>(null);
  public planillaConstants = this.constantsSubject.asObservable();

  constructor(private http: HttpClient) {}

  loadConstants(): Observable<any> {
    return this.http.get(`${this.apiUrl}/constants/planilla`).pipe(
      tap((constants) => {
        this.constantsSubject.next(constants);
        this.saveConstantsToLocalStorage(constants);
      })
    );
  }

  private saveConstantsToLocalStorage(constants: any) {
    localStorage.setItem('PLANILLA_CONSTANTS', JSON.stringify(constants));
  }

  getConstants(): any {
    const constants = localStorage.getItem('PLANILLA_CONSTANTS');
    return constants ? JSON.parse(constants) : null;
  }
}
