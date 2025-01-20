import { Injectable } from '@angular/core';
import {
  HttpClient,
  HttpHeaders,
  HttpResponse,
  HttpErrorResponse,
} from '@angular/common/http';
import { map, catchError, retry } from 'rxjs/operators';
import { Observable, throwError } from 'rxjs';
import { AlertService } from '@services/alert.service';
import { environment } from './../../environments/environment';

import * as moment from 'moment';
declare let $: any;

@Injectable()
export class ApiService {
  public appUrl: string = environment.APP_URL;
  public baseUrl: string = environment.API_URL;
  public apiUrl = this.baseUrl + '/api/';
  private currentUserPermissions: {
    rolePermissions: string[];
    directPermissions: string[];
    revokedPermissions: string[];
    effectivePermissions: string[];
  } = {
    rolePermissions: [],
    directPermissions: [],
    revokedPermissions: [],
    effectivePermissions: [],
  };

  constructor(private http: HttpClient, private alertService: AlertService) {}

  getToUrl(url: string) {
    return this.http.get<any>(url).pipe(retry(0), catchError(this.handleError));
  }

  getAll(url: string, filtros: any = {}) {
    return this.http
      .get<any>(this.apiUrl + url, { params: filtros })
      .pipe(retry(0), catchError(this.handleError));
  }

  read(url: string, id: number) {
    return this.http
      .get<any>(this.apiUrl + url + id)
      .pipe(retry(0), catchError(this.handleError));
  }

  filter(url: string, filter: any) {
    return this.http
      .get<any>(this.apiUrl + url + filter)
      .pipe(retry(0), catchError(this.handleError));
  }

  store(url: string, model: any) {
    return this.http
      .post<any>(this.apiUrl + url, model)
      .pipe(retry(0), catchError(this.handleError));
  }
  update(url: string, id: number, model: any) {
    return this.http
      .put<any>(`${this.apiUrl}${url}/${id}`, model)
      .pipe(retry(0), catchError(this.handleError));
  }

  delete(url: string, id: number) {
    return this.http
      .delete<any>(this.apiUrl + url + id)
      .pipe(retry(0), catchError(this.handleError));
  }

  paginate(url: string, filtros: any = {}) {
    return this.http
      .get<any>(url, { params: filtros })
      .pipe(retry(0), catchError(this.handleError));
  }

  upload(url: string, formData: any) {
    let headers = new HttpHeaders();
    headers.append('Accept', 'application/json');
    headers.append(
      'Authorization',
      'Bearer ' + JSON.parse(localStorage.getItem('SP_token')!)
    );
    let options = { headers };
    return this.http
      .post(this.apiUrl + url, formData, options)
      .pipe(retry(0), catchError(this.handleError));
  }

  // login(user:any) {return this.http.post<any>(this.apiUrl + 'login', user).pipe(map((response: HttpResponse<any>) => {let data:any = response; if (data.token && data.user) {localStorage.setItem('SP_token', JSON.stringify(data.token)); localStorage.setItem('SP_auth_user', JSON.stringify(data.user)); } })); }

  register(user: any) {
    return this.http.post<any>(this.apiUrl + 'register', user).pipe(
      map((response: HttpResponse<any>) => {
        let data: any = response;
        if (data) {
          localStorage.setItem('SP_user_register', JSON.stringify(data));
        }
      })
    );
  }

  export(url: string, filtros: any): Observable<Blob> {
    return this.http.get(this.apiUrl + url, {
      responseType: 'blob',
      params: filtros,
    });
  }

  login(user: any) {
    return this.http.post<any>(this.apiUrl + 'login', user).pipe(
      map((response: HttpResponse<any>) => {
        let data: any = response;
        if (data.token && data.user) {
          localStorage.setItem('SP_token', JSON.stringify(data.token));
          localStorage.setItem('SP_auth_user', JSON.stringify(data.user));

          // Cargar permisos después del login
          this.loadUserPermissions(data.user.id);
        }
        return data;
      })
    );
  }

  loadUserPermissions(userId: number) {
    this.getAll(`roles-permissions/user/${userId}`).subscribe(
      (response: any) => {
        if (response.ok) {
          const permissions = {
            rolePermissions: response.data.rolePermissions || [],
            directPermissions: response.data.directPermissions || [],
            revokedPermissions: response.data.revokedPermissions || [],
            effectivePermissions: response.data.effectivePermissions || [],
            role: response.data.role || '',
          };

          localStorage.setItem(
            'SP_user_permissions',
            JSON.stringify(permissions)
          );
          this.currentUserPermissions = permissions;
        }
      }
    );
  }

  logout() {
    let data: any = {};
    if (this.autenticated()) {
      data.usuario_id = this.auth_user().id;
      this.store('logout', data).subscribe(
        (ivas) => {},
        (error) => {
          this.alertService.error(error);
        }
      );
    }
    localStorage.clear();
    this.currentUserPermissions = {
      rolePermissions: [],
      directPermissions: [],
      revokedPermissions: [],
      effectivePermissions: [],
    };
  }

  saludar() {
    var hours = new Date().getHours();
    if (hours >= 12 && hours < 18) {
      return 'Buenas tardes';
    } else if (hours >= 18) {
      return 'Buenas noches';
    } else {
      return 'Buenos días';
    }
  }

  autenticated() {
    let token = JSON.parse(localStorage.getItem('SP_token')!);
    if (token) {
      return true;
    } else {
      return false;
    }
  }

  auth_user() {
    return JSON.parse(localStorage.getItem('SP_auth_user')!);
  }
  register_user() {
    return JSON.parse(localStorage.getItem('SP_user_register')!);
  }

  auth_token() {
    return JSON.parse(localStorage.getItem('SP_token')!);
  }

  date(): string {
    let today = new Date();
    let dd = today.getDate();
    let mm = today.getMonth() + 1;
    let d;
    let m;
    var yyyy = today.getFullYear();
    if (dd < 10) {
      d = '0' + dd;
    } else {
      d = dd;
    }
    if (mm < 10) {
      m = '0' + mm;
    } else {
      m = mm;
    }
    let date: string = yyyy + '-' + m + '-' + d;
    return date;
  }

  dataURItoBlob(dataURI: any) {
    let byteString: any;
    if (dataURI.split(',')[0].indexOf('base64') >= 0) {
      byteString = atob(dataURI.split(',')[1]);
    } else {
      byteString = unescape(dataURI.split(',')[1]);
    }
    const mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];
    const ia = new Uint8Array(byteString.length);
    for (let i = 0; i < byteString.length; i++) {
      ia[i] = byteString.charCodeAt(i);
    }
    return new Blob([ia], { type: mimeString });
  }

  datetime(): string {
    let today = new Date();
    let dd = today.getDate();
    let mm = today.getMonth() + 1;
    let hh = today.getHours();
    let min = today.getMinutes();
    let sec = today.getSeconds();
    let d;
    let m;
    let h;
    let se;
    var yyyy = today.getFullYear();
    if (dd < 10) {
      d = '0' + dd;
    } else {
      d = dd;
    }
    if (mm < 10) {
      m = '0' + mm;
    } else {
      m = mm;
    }
    if (sec < 10) {
      se = '0' + sec;
    } else {
      se = sec;
    }
    let datetime: string =
      yyyy + '-' + m + '-' + d + ' ' + hh + ':' + min + ':' + se;
    return datetime;
  }

  slug(str: any) {
    if (str) {
      str = str.replace(/^\s+|\s+$/g, '');
      str = str.toLowerCase();
      var from = 'àáäâèéëêìíïîòóöôùúüûñç·/_,:;';
      var to = 'aaaaeeeeiiiioooouuuunc------';
      for (var i = 0, l = from.length; i < l; i++) {
        str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
      }
      str = str
        .replace(/[^a-z0-9 -]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
      return str;
    }
  }

  toggleTheme() {
    if (localStorage.getItem('SP_theme') == 'light') {
      localStorage.setItem('SP_theme', 'dark');
    } else {
      localStorage.setItem('SP_theme', 'light');
    }
    this.loadTheme();
  }

  loadTheme() {
    let theme: any = localStorage.getItem('SP_theme');
    if (!theme) {
      localStorage.setItem('SP_theme', 'light');
    }
    if (localStorage.getItem('SP_theme') == 'dark') {
      $('body').attr('data-theme-version', 'dark');
      $('.icon-theme').removeClass('far');
      $('.icon-theme').addClass('fas');
    } else {
      $('body').attr('data-theme-version', 'light');
      $('.icon-theme').removeClass('fas');
      $('.icon-theme').addClass('far');
    }
  }

  loadData() {
    this.getAll('formas-de-pago').subscribe(
      (metodospago) => {
        localStorage.setItem('metodospago', JSON.stringify(metodospago));
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.getAll('paises').subscribe(
      (paises) => {
        localStorage.setItem('paises', JSON.stringify(paises));
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.getAll('municipios').subscribe(
      (municipios) => {
        localStorage.setItem('municipios', JSON.stringify(municipios));
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.getAll('distritos').subscribe(
      (distritos) => {
        localStorage.setItem('distritos', JSON.stringify(distritos));
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.getAll('departamentos').subscribe(
      (departamentos) => {
        localStorage.setItem('departamentos', JSON.stringify(departamentos));
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.getAll('actividades_economicas').subscribe(
      (actividad_economicas) => {
        localStorage.setItem(
          'actividad_economicas',
          JSON.stringify(actividad_economicas)
        );
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.getAll('unidades').subscribe(
      (medidas) => {
        localStorage.setItem('unidades_medidas', JSON.stringify(medidas));
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  isAdmin() {
    let usuario = this.auth_user();
    if (
      usuario.tipo == 'Administrador' ||
      usuario.tipo == 'Contador' ||
      usuario.tipo == 'Supervisor'
    )
      return true;
    return false;
  }

  canEdit() {
    let usuario = this.auth_user();
    if (usuario.tipo == 'Administrador' || usuario.tipo == 'Supervisor')
      return true;
    return false;
  }
  canCreate() {
    let usuario = this.auth_user();
    if (usuario.tipo == 'Administrador' || usuario.tipo == 'Supervisor')
      return true;
    return false;
  }

  canDelete() {
    let usuario = this.auth_user();
    if (usuario.tipo == 'Administrador' || usuario.tipo == 'Supervisor')
      return true;
    return false;
  }

  canCreateTest(permission: string): boolean {
    return this.hasPermission(permission);
  }

  //   canDelete(permission: string): boolean {
  //     return this.hasPermission(permission);
  //   }
  canEditTest(permission: string): boolean {
    return this.hasPermission(permission);
  }

  getModules() {
    return this.http
      .get<any>(this.apiUrl + 'modules')
      .pipe(retry(0), catchError(this.handleError));
  }

  hasPermission(permission: string): boolean {
    if (this.currentUserPermissions.effectivePermissions.length === 0) {
      const storedPermissions = localStorage.getItem('SP_user_permissions');
      if (storedPermissions) {
        this.currentUserPermissions = JSON.parse(storedPermissions);
      }
    }

    const effectivePermissions = Array.isArray(
      this.currentUserPermissions.effectivePermissions
    )
      ? this.currentUserPermissions.effectivePermissions
      : Object.values(this.currentUserPermissions.effectivePermissions);

    const revokedPermissions = Array.isArray(
      this.currentUserPermissions.revokedPermissions
    )
      ? this.currentUserPermissions.revokedPermissions
      : Object.values(this.currentUserPermissions.revokedPermissions);

    if (revokedPermissions.includes(permission)) {
      return false;
    }

    return effectivePermissions.includes(permission);
  }

  hasAnyPermission(permissions: string[]): boolean {
    return permissions.some((permission) => this.hasPermission(permission));
  }
  canAccessModule(moduleName: string): boolean {
    return this.hasPermission(`${moduleName}.acceder`);
  }

  generateGoogleCalendarLink(event: any): string {
    const startDate = moment(event.startDate)
      .utc()
      .format('YYYYMMDDTHHmmss[Z]');
    const endDate = moment(event.endDate).utc().format('YYYYMMDDTHHmmss[Z]');
    const calendarLink = `https://www.google.com/calendar/event?action=TEMPLATE&dates=${startDate}/${endDate}&text=${encodeURIComponent(
      event.title
    )}&details=${encodeURIComponent(
      event.description
    )}&location=${encodeURIComponent(event.location)}`;
    return calendarLink;
  }

  getPosition(): Promise<any> {
    return new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(
        (resp) => {
          resolve({ lng: resp.coords.longitude, lat: resp.coords.latitude });
        },
        (err) => {
          reject(err);
        }
      );
    });
  }

  private handleError(error: HttpErrorResponse) {
    return throwError(error);
  }
}
