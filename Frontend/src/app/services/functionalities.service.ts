import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of, Subject } from 'rxjs';
import { catchError, map, shareReplay, tap } from 'rxjs/operators';
import { environment } from 'src/environments/environment';

@Injectable({
  providedIn: 'root'
})
export class FuncionalidadesService {
  private accesoCache: { [key: string]: boolean } = {};
  private readonly cambiosSubject = new Subject<void>();
  private cargandoAccesos$: Observable<boolean> | null = null;
  private accesosCargados = false;

  constructor(
    private http: HttpClient
  ) { }

  /** Emite cuando se invalida la caché (p. ej. tras guardar funcionalidades en Super Admin). */
  onCambios(): Observable<void> {
    return this.cambiosSubject.asObservable();
  }

  verificarAcceso(slug: string): Observable<boolean> {
    // ponytail: Si ya cargamos todos los accesos globales, resolver sincrónicamente
    if (this.accesosCargados) {
      return of(!!this.accesoCache[slug]);
    }

    // Si ya hay una petición de carga global en progreso, suscribirse a la misma
    if (this.cargandoAccesos$) {
      return this.cargandoAccesos$.pipe(
        map(() => !!this.accesoCache[slug])
      );
    }

    // Iniciar carga global de todos los accesos activos de la empresa
    this.cargandoAccesos$ = this.http.get<{ accesos: string[] }>(`${environment.API_URL}/api/verificar-accesos`).pipe(
      map(response => {
        this.accesoCache = {};
        response.accesos.forEach(s => {
          this.accesoCache[s] = true;
        });
        this.accesosCargados = true;
        this.cargandoAccesos$ = null;
        return true;
      }),
      catchError(error => {
        console.error('Error al cargar accesos a funcionalidades globales:', error);
        this.cargandoAccesos$ = null;
        return of(false);
      }),
      shareReplay(1)
    );

    return this.cargandoAccesos$.pipe(
      map(() => !!this.accesoCache[slug])
    );
  }

  limpiarCache(): void {
    this.accesoCache = {};
    this.accesosCargados = false;
    this.cargandoAccesos$ = null;
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