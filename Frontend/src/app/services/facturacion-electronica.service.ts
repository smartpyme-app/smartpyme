import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

/**
 * Servicio genérico de Facturación Electrónica Multi-País
 * 
 * Reemplaza MHService con soporte para múltiples países (El Salvador, Costa Rica, etc.)
 * 
 * @deprecated Los métodos individuales están marcados como deprecated cuando corresponden a funcionalidad específica
 */
@Injectable({
  providedIn: 'root'
})
export class FacturacionElectronicaService {

  constructor(
    private http: HttpClient,
    private alertService: AlertService,
    private apiService: ApiService
  ) { }

  /**
   * Obtiene la configuración de facturación electrónica del país de la empresa
   * 
   * @returns Configuración del país o null si no está configurado
   */
  private obtenerConfiguracionPais(): any {
    const user = JSON.parse(localStorage.getItem('SP_auth_user') || '{}');
    const empresa = user?.empresa;

    if (!empresa) {
      return null;
    }

    // Obtener código de país (nuevo campo genérico o fallback a cod_pais)
    const codPais = empresa.fe_pais || empresa.cod_pais || 'SV';
    
    // Por ahora, la configuración está en el backend
    // En el futuro se podría cachear aquí
    return {
      codPais: codPais,
      empresa: empresa
    };
  }

  /**
   * Obtiene la URL base según el ambiente y país
   * 
   * @param tipo Tipo de URL (auth, recepcion, anulacion, consulta, firmador)
   * @returns URL completa
   */
  private obtenerUrlBase(tipo: string): string {
    const config = this.obtenerConfiguracionPais();
    
    if (!config) {
      throw new Error('No se pudo obtener la configuración del país');
    }

    const ambiente = config.empresa.fe_ambiente || '00';
    const esProduccion = ambiente === '01';
    
    // Por ahora, las URLs están en el backend
    // El frontend usa las rutas del API que ya manejan el país
    const baseUrl = localStorage.getItem('SP_api_url') || '';
    
    // Para El Salvador, mantener compatibilidad con URLs antiguas
    if (config.codPais === 'SV') {
      const mhUrlBase = localStorage.getItem('SP_mh_url_base') || '';
      
      switch (tipo) {
        case 'auth':
          return `${mhUrlBase}/seguridad/auth`;
        case 'recepcion':
          return `${mhUrlBase}/fesv/recepciondte`;
        case 'anulacion':
          return `${mhUrlBase}/fesv/anulardte`;
        case 'consulta':
          return `${mhUrlBase}/fesv/recepcion/consultadte`;
        case 'firmador':
          // URL del firmador (puede venir de configuración o usar default)
          return config.empresa.fe_configuracion?.url_firmador_alternativa || 
                 'https://facturadtesv.com:8443/firmardocumento/';
        default:
          return mhUrlBase;
      }
    }
    
    // Para otros países, usar rutas del API genérico
    return `${baseUrl}/fe/${tipo}`;
  }

  /**
   * Autentica con la API de facturación electrónica del país
   * 
   * @returns Observable con el token de autenticación
   */
  auth(): Observable<any> {
    const config = this.obtenerConfiguracionPais();
    
    if (!config) {
      return throwError(() => new Error('Usuario no autenticado'));
    }

    const empresa = config.empresa;
    
    // Usar campos genéricos con fallback a campos antiguos
    const usuario = empresa.fe_usuario || empresa.mh_usuario;
    const contrasena = empresa.fe_contrasena || empresa.mh_contrasena;

    if (!usuario || !contrasena) {
      return throwError(() => new Error('Configure el usuario y contraseña para conectarse a la API de facturación electrónica'));
    }

    const formData = new FormData();
    formData.append('user', usuario.replace(/-/g, ''));
    formData.append('pwd', contrasena);

    const url = this.obtenerUrlBase('auth');
    
    return this.http.post<any>(url, formData).pipe(
      map(response => {
        // Guardar token en localStorage
        if (response.body && response.body.token) {
          localStorage.setItem('SP_token_fe', JSON.stringify(response.body));
        }
        return response;
      }),
      catchError(error => {
        console.error('Error en autenticación FE:', error);
        return throwError(() => error);
      })
    );
  }

  /**
   * Inicia sesión y guarda el token
   */
  login(): void {
    this.auth().subscribe({
      next: (data) => {
        if (data.body && data.body.token) {
          localStorage.setItem('SP_token_fe', JSON.stringify(data.body));
        }
      },
      error: (error) => {
        this.alertService.error(error);
      }
    });
  }

  /**
   * Firma electrónicamente un DTE
   * 
   * @param DTE Documento a firmar
   * @returns Observable con el documento firmado
   */
  firmarDTE(DTE: any): Observable<any> {
    const config = this.obtenerConfiguracionPais();
    
    if (!config) {
      return throwError(() => new Error('Usuario no autenticado, vuelva a iniciar sesión'));
    }

    const empresa = config.empresa;

    if (!empresa?.nit) {
      return throwError(() => new Error('NIT no configurado en la información de la cuenta'));
    }

    // Usar campo genérico con fallback
    const passwordCertificado = empresa.fe_certificado_password || empresa.mh_pwd_certificado;

    if (!passwordCertificado) {
      return throwError(() => new Error('Contraseña del certificado no configurada en los datos de facturación electrónica'));
    }

    const formData: any = {
      nit: empresa.nit.replace(/-/g, ''),
      activo: true,
      passwordPri: passwordCertificado,
      dteJson: DTE
    };

    const url = this.obtenerUrlBase('firmador');
    
    return this.http.post<any>(url, formData, { params: { saltarJWT: true } }).pipe(
      catchError(error => {
        console.error('Error al firmar DTE:', error);
        return throwError(() => error);
      })
    );
  }

  /**
   * Envía un DTE firmado a la autoridad tributaria
   * 
   * @param documento Documento (venta, devolución, etc.)
   * @param dteFirmado DTE firmado
   * @returns Observable con la respuesta
   */
  enviarDTE(documento: any, dteFirmado: any): Observable<any> {
    const token = JSON.parse(localStorage.getItem('SP_token_fe') || 'null');
    
    if (!token || !token.token) {
      return throwError(() => new Error('Token de facturación electrónica no creado. Por favor, autentíquese primero.'));
    }

    const headers = new HttpHeaders({
      'Content-Type': 'application/json',
      'User-Agent': 'Angular',
      'Authorization': token.token,
    });

    const formData: any = {
      ambiente: documento.dte.identificacion.ambiente,
      idEnvio: documento.id,
      version: documento.dte.identificacion.version,
      tipoDte: documento.dte.identificacion.tipoDte,
      documento: dteFirmado,
      codigoGeneracion: documento.dte.identificacion.codigoGeneracion || documento.dte.codigoGeneracion
    };

    const url = this.obtenerUrlBase('recepcion');
    
    return this.http.post<any>(url, formData, { headers, params: { saltarJWT: true } }).pipe(
      catchError(error => {
        console.error('Error al enviar DTE:', error);
        return throwError(() => error);
      })
    );
  }

  /**
   * Consulta el estado de un DTE
   * 
   * @param documento Documento con DTE
   * @returns Observable con el estado del DTE
   */
  consultarDTE(documento: any): Observable<any> {
    const config = this.obtenerConfiguracionPais();
    const token = JSON.parse(localStorage.getItem('SP_token_fe') || 'null');

    if (!config) {
      return throwError(() => new Error('Usuario no autenticado'));
    }

    const headers = new HttpHeaders({
      'Content-Type': 'application/json',
      'User-Agent': 'Angular',
      'Authorization': token?.token || '',
    });

    const formData: any = {
      nitEmisor: config.empresa.nit.replace(/-/g, ''),
      tdte: documento.dte.identificacion.tipoDte,
      codigoGeneracion: documento.dte.identificacion.codigoGeneracion || documento.dte.codigo_generacion
    };

    const url = this.obtenerUrlBase('consulta');
    
    return this.http.post<any>(url, formData, { headers, params: { saltarJWT: true } }).pipe(
      catchError(error => {
        console.error('Error al consultar DTE:', error);
        return throwError(() => error);
      })
    );
  }

  /**
   * Anula un DTE
   * 
   * @param documento Documento con DTE a anular
   * @param dteFirmado DTE de anulación firmado
   * @returns Observable con la respuesta
   */
  anularDTE(documento: any, dteFirmado: any): Observable<any> {
    const token = JSON.parse(localStorage.getItem('SP_token_fe') || 'null');

    if (!token || !token.token) {
      return throwError(() => new Error('Token de facturación electrónica no creado'));
    }

    const headers = new HttpHeaders({
      'Content-Type': 'application/json',
      'User-Agent': 'Angular',
      'Authorization': token.token,
    });

    const dteInvalidacion = documento.dte_invalidacion || documento.dte;
    
    const formData: any = {
      ambiente: dteInvalidacion.identificacion.ambiente,
      idEnvio: documento.id,
      version: dteInvalidacion.identificacion.version,
      documento: dteFirmado
    };

    const url = this.obtenerUrlBase('anulacion');
    
    return this.http.post<any>(url, formData, { headers, params: { saltarJWT: true } }).pipe(
      catchError(error => {
        console.error('Error al anular DTE:', error);
        return throwError(() => error);
      })
    );
  }

  /**
   * Emite un DTE completo (genera, firma y envía)
   * 
   * @param documento Documento a emitir (venta, compra, etc.)
   * @param endpoint Endpoint del API para generar DTE (ej: 'generarDTE', 'generarDTENotaCredito')
   * @param endpointGuardar Endpoint para guardar el documento actualizado
   * @returns Promise con el documento actualizado
   */
  emitirDTE(documento: any, endpoint: string = 'generarDTE', endpointGuardar: string = 'venta'): Promise<any> {
    return new Promise((resolve, reject) => {
      // Usar nuevo endpoint con prefijo /fe/
      const endpointCompleto = `fe/${endpoint}`;
      
      this.apiService.store(endpointCompleto, documento).subscribe({
        next: (dte) => {
          documento.dte = dte;
          
          this.firmarDTE(dte).subscribe({
            next: (dteFirmado) => {
              if (dteFirmado.status === 'ERROR') {
                reject(dteFirmado.body?.mensaje || 'No se pudo firmar el DTE');
                return;
              }

              documento.dte.firmaElectronica = dteFirmado.body;
              
              this.enviarDTE(documento, dteFirmado.body).subscribe({
                next: (respuesta) => {
                  if ((respuesta.estado === 'PROCESADO' || respuesta.estado === 'RECIBIDO') && respuesta.selloRecibido) {
                    documento.dte.sello = respuesta.selloRecibido;
                    documento.dte.selloRecibido = respuesta.selloRecibido;
                    documento.sello_mh = respuesta.selloRecibido;
                    documento.tipo_dte = respuesta.tipoDte || documento.dte.identificacion.tipoDte;
                    documento.numero_control = respuesta.numeroControl || documento.dte.identificacion.numeroControl;
                    documento.codigo_generacion = respuesta.codigoGeneracion || documento.dte.identificacion.codigoGeneracion;
                    
                    this.apiService.store(endpointGuardar, documento).subscribe({
                      next: (data) => resolve(data),
                      error: (error) => {
                        this.alertService.error(error);
                        reject(error);
                      }
                    });
                  } else {
                    reject(respuesta.observaciones || respuesta.descripcionMsg || 'Error al procesar DTE');
                  }
                },
                error: (error) => {
                  const mensaje = error.error?.observaciones?.[0] || 
                                 error.error?.descripcionMsg || 
                                 error.message || 
                                 'Error al enviar DTE';
                  reject(mensaje);
                }
              });
            },
            error: (error) => {
              reject(error);
            }
          });
        },
        error: (error) => {
          reject(error);
        }
      });
    });
  }

  /**
   * Emite un DTE de Nota de Crédito o Débito
   * 
   * @param devolucion Devolución a emitir
   * @returns Promise con la devolución actualizada
   */
  emitirDTENotaCredito(devolucion: any): Promise<any> {
    return this.emitirDTE(
      devolucion,
      'generarDTENotaCredito',
      'devolucion/venta'
    );
  }

  /**
   * Verifica el estado del firmador
   * 
   * @returns Observable con el estado
   */
  verificarFirmador(): Observable<any> {
    const url = this.obtenerUrlBase('firmador');
    return this.http.get<any>(`${url}status`, { observe: 'response' });
  }
}
