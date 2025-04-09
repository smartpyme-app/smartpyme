import { Injectable, Inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { catchError, map, tap } from 'rxjs/operators';
import { environment } from 'src/environments/environment';

@Injectable({
  providedIn: 'root'
})
export class FuncionalidadesService {
  // Cache para evitar peticiones repetidas
  private accesoCache: { [key: string]: boolean } = {};

  constructor(
    private http: HttpClient
  ) { }

  verificarAcceso(slug: string): Observable<boolean> {
    // Si ya está en caché, devolver el resultado directamente
    if (this.accesoCache[slug] !== undefined) {
      return of(this.accesoCache[slug]);
    }

    // Si no está en caché, consultar a la API
    return this.http.get<{ acceso: boolean }>(`${environment.API_URL}/api/verificar-acceso/${slug}`).pipe(
      map(response => response.acceso),
      tap(acceso => {
        // Guardar en caché
        this.accesoCache[slug] = acceso;
      }),
      catchError(error => {
        console.error(`Error al verificar acceso a funcionalidad ${slug}:`, error);
        return of(false); // Por defecto, denegar acceso en caso de error
      })
    );
  }

  limpiarCache(): void {
    this.accesoCache = {};
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