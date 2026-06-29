import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of, Subject } from 'rxjs';
import { catchError, map, tap, shareReplay } from 'rxjs/operators';
import { environment } from 'src/environments/environment';

@Injectable({
  providedIn: 'root'
})
export class FuncionalidadesService {
  private accesoCache: { [key: string]: boolean } = {};
  private readonly cambiosSubject = new Subject<void>();
  private accesosCargados = false;
  private cargando$: Observable<string[]> | null = null;

  constructor(
    private http: HttpClient
  ) { }

  /** Emite cuando se invalida la caché (p. ej. tras guardar funcionalidades en Super Admin). */
  onCambios(): Observable<void> {
    return this.cambiosSubject.asObservable();
  }

  verificarAcceso(slug: string): Observable<boolean> {
    // Si ya está cargada la caché global, responder inmediatamente
    if (this.accesosCargados) {
      return of(!!this.accesoCache[slug]);
    }

    // Si ya hay una petición en curso, reusar el mismo flujo
    if (this.cargando$) {
      return this.cargando$.pipe(
        map(accesos => accesos.includes(slug))
      );
    }

    // Consultar todos los accesos en una sola llamada
    this.cargando$ = this.http.get<{ accesos: string[] }>(`${environment.API_URL}/api/verificar-accesos`).pipe(
      map(response => response.accesos || []),
      tap(accesos => {
        this.accesoCache = {};
        accesos.forEach(s => {
          this.accesoCache[s] = true;
        });
        this.accesosCargados = true;
        this.cargando$ = null;
      }),
      catchError(error => {
        console.error('Error al verificar accesos globales:', error);
        this.cargando$ = null;
        return of([]);
      }),
      shareReplay(1)
    );

    return this.cargando$.pipe(
      map(accesos => accesos.includes(slug))
    );
  }

  limpiarCache(): void {
    this.accesoCache = {};
    this.accesosCargados = false;
    this.cargando$ = null;
    this.cambiosSubject.next();
  }

  tieneAccesoCacheado(slug: string): boolean {
    return this.accesoCache[slug] === true;
  }


  obtenerConfiguracion(slug: string): Observable<any> {
    return this.http.get<{ configuracion: any }>(`${environment.API_URL}/api/configuracion-funcionalidad/${slug}`).pipe(
      map(response => response.configuracion),
      catchError(error => {
        console.error(`Error al obtener configuración de funcionalidad ${slug}:`, error);
        return of(null);
      })
    );
  }
}