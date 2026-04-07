import { Injectable } from '@angular/core';
import * as moment from 'moment';
import { HttpService } from '@services/http.service';
import { AlertService } from '@services/alert.service';
import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

declare let $: any;

@Injectable({
  providedIn: 'root'
})
export class UtilityService {
  constructor(
    private httpService: HttpService,
    private alertService: AlertService
  ) {}

  saludar(): string {
    var hours = new Date().getHours();
    if (hours >= 12 && hours < 18) {
      return 'Buenas tardes';
    } else if (hours >= 18) {
      return 'Buenas noches';
    } else {
      return 'Buenos días';
    }
  }

  date(): string {
    let today = new Date();
    let dd = today.getDate();
    let mm = today.getMonth() + 1;
    let d: string;
    let m: string;
    var yyyy = today.getFullYear();
    if (dd < 10) {
      d = '0' + dd;
    } else {
      d = dd.toString();
    }
    if (mm < 10) {
      m = '0' + mm;
    } else {
      m = mm.toString();
    }
    let date: string = yyyy + '-' + m + '-' + d;
    return date;
  }

  datetime(): string {
    let today = new Date();
    let dd = today.getDate();
    let mm = today.getMonth() + 1;
    let hh = today.getHours();
    let min = today.getMinutes();
    let sec = today.getSeconds();
    let d: string;
    let m: string;
    let h: string;
    let se: string;
    var yyyy = today.getFullYear();
    if (dd < 10) {
      d = '0' + dd;
    } else {
      d = dd.toString();
    }
    if (mm < 10) {
      m = '0' + mm;
    } else {
      m = mm.toString();
    }
    if (sec < 10) {
      se = '0' + sec;
    } else {
      se = sec.toString();
    }
    let datetime: string =
      yyyy + '-' + m + '-' + d + ' ' + hh + ':' + min + ':' + se;
    return datetime;
  }

  dataURItoBlob(dataURI: any): Blob {
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

  slug(str: any): string | undefined {
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
    return undefined;
  }

  toggleTheme(): void {
    if (localStorage.getItem('SP_theme') == 'light') {
      localStorage.setItem('SP_theme', 'dark');
    } else {
      localStorage.setItem('SP_theme', 'light');
    }
    this.loadTheme();
  }

  loadTheme(): void {
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

  downloadFile(blob: Blob, filename: string): void {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    window.URL.revokeObjectURL(url);
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

  /**
   * Catálogo MH (SV) o DGT (CR): mismos nombres de recurso y localStorage.
   */
  private prefijoUbicacion(): string {
    try {
      const raw = localStorage.getItem('SP_auth_user');
      const u = raw ? JSON.parse(raw) : null;
      return resolveCodigoPaisFe(u?.empresa) === FE_PAIS_CR ? 'fe-cr/' : '';
    } catch {
      return '';
    }
  }

  loadData(): void {
    const ub = this.prefijoUbicacion();
    this.httpService.getAll('formas-de-pago').subscribe(
      (metodospago: any) => {
        localStorage.setItem('metodospago', JSON.stringify(metodospago));
      },
      (error: any) => {
        this.alertService.error(error);
      }
    );

    this.httpService.getAll('paises').subscribe(
      (paises: any) => {
        localStorage.setItem('paises', JSON.stringify(paises));
      },
      (error: any) => {
        this.alertService.error(error);
      }
    );

    this.httpService.getAll(ub + 'municipios').subscribe(
      (municipios: any) => {
        localStorage.setItem('municipios', JSON.stringify(municipios));
      },
      (error: any) => {
        this.alertService.error(error);
      }
    );

    this.httpService.getAll(ub + 'distritos').subscribe(
      (distritos: any) => {
        localStorage.setItem('distritos', JSON.stringify(distritos));
      },
      (error: any) => {
        this.alertService.error(error);
      }
    );

    this.httpService.getAll(ub + 'departamentos').subscribe(
      (departamentos: any) => {
        localStorage.setItem('departamentos', JSON.stringify(departamentos));
      },
      (error: any) => {
        this.alertService.error(error);
      }
    );

    this.httpService.getAll('actividades_economicas').subscribe(
      (actividad_economicas: any) => {
        localStorage.setItem('actividad_economicas', JSON.stringify(actividad_economicas));
      },
      (error: any) => {
        this.alertService.error(error);
      }
    );

    this.httpService.getAll('unidades').subscribe(
      (medidas: any) => {
        localStorage.setItem('unidades_medidas', JSON.stringify(medidas));
      },
      (error: any) => {
        this.alertService.error(error);
      }
    );
  }

  getConstants(): any {
    const constants = localStorage.getItem('SP_constants');
    return constants ? JSON.parse(constants) : null;
  }
}

