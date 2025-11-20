import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, of } from 'rxjs';
import { tap, catchError } from 'rxjs/operators';
import { HttpService } from './http.service';
import { HttpCacheService } from './http-cache.service';

interface SharedDataCache {
  [key: string]: {
    data: any[];
    loading: boolean;
    timestamp: number;
  };
}

@Injectable({
  providedIn: 'root'
})
export class SharedDataService {
  private cache: SharedDataCache = {};
  private loadingSubjects: { [key: string]: BehaviorSubject<boolean> } = {};
  private dataSubjects: { [key: string]: BehaviorSubject<any[]> } = {};

  // TTL para diferentes tipos de datos (en milisegundos)
  private readonly TTL = {
    LISTAS_REFERENCIA: 10 * 60 * 1000, // 10 minutos
    DATOS_DINAMICOS: 5 * 60 * 1000,    // 5 minutos
  };

  constructor(
    private httpService: HttpService,
    private cacheService: HttpCacheService
  ) {}

  /**
   * Obtiene sucursales
   */
  getSucursales(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('sucursales/list', 'sucursales', forceRefresh);
  }

  /**
   * Obtiene formas de pago
   */
  getFormasDePago(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('formas-de-pago/list', 'formasDePago', forceRefresh);
  }

  /**
   * Obtiene documentos
   */
  getDocumentos(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('documentos/list', 'documentos', forceRefresh);
  }

  /**
   * Obtiene documentos por nombre
   */
  getDocumentosPorNombre(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('documentos/list-nombre', 'documentosPorNombre', forceRefresh);
  }

  /**
   * Obtiene usuarios
   */
  getUsuarios(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('usuarios/list', 'usuarios', forceRefresh);
  }

  /**
   * Obtiene proyectos
   */
  getProyectos(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('proyectos/list', 'proyectos', forceRefresh);
  }

  /**
   * Obtiene categorías
   */
  getCategorias(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('categorias/list', 'categorias', forceRefresh);
  }

  /**
   * Obtiene marcas
   */
  getMarcas(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('marcas/list', 'marcas', forceRefresh);
  }

  /**
   * Obtiene clientes
   */
  getClientes(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('clientes/list', 'clientes', forceRefresh);
  }

  /**
   * Obtiene proveedores
   */
  getProveedores(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('proveedores/list', 'proveedores', forceRefresh);
  }

  /**
   * Obtiene canales
   */
  getCanales(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('canales/list', 'canales', forceRefresh);
  }

  /**
   * Obtiene bodegas
   */
  getBodegas(forceRefresh: boolean = false): Observable<any[]> {
    return this.getSharedData('bodegas/list', 'bodegas', forceRefresh);
  }

  /**
   * Método genérico para obtener datos compartidos
   */
  private getSharedData(endpoint: string, cacheKey: string, forceRefresh: boolean = false): Observable<any[]> {
    // Inicializar BehaviorSubject si no existe
    if (!this.dataSubjects[cacheKey]) {
      this.dataSubjects[cacheKey] = new BehaviorSubject<any[]>([]);
      this.loadingSubjects[cacheKey] = new BehaviorSubject<boolean>(false);
    }

    // Verificar si hay datos en cache y no se fuerza refresh
    if (!forceRefresh && this.cache[cacheKey]) {
      const cached = this.cache[cacheKey];
      const now = Date.now();
      const ttl = this.TTL.LISTAS_REFERENCIA;

      // Si el cache es válido, retornar datos desde cache
      if (now - cached.timestamp < ttl && cached.data.length > 0) {
        this.dataSubjects[cacheKey].next(cached.data);
        return this.dataSubjects[cacheKey].asObservable();
      }
    }

    // Si ya está cargando, retornar el observable existente
    if (this.cache[cacheKey]?.loading) {
      return this.dataSubjects[cacheKey].asObservable();
    }

    // Marcar como cargando
    if (!this.cache[cacheKey]) {
      this.cache[cacheKey] = { data: [], loading: false, timestamp: 0 };
    }
    this.cache[cacheKey].loading = true;
    this.loadingSubjects[cacheKey].next(true);

    // Hacer la petición HTTP
    return this.httpService.getAll(endpoint).pipe(
      tap(data => {
        // Guardar en cache
        this.cache[cacheKey] = {
          data: Array.isArray(data) ? data : [],
          loading: false,
          timestamp: Date.now()
        };

        // Emitir nuevos datos
        this.dataSubjects[cacheKey].next(this.cache[cacheKey].data);
        this.loadingSubjects[cacheKey].next(false);
      }),
      catchError(error => {
        this.cache[cacheKey].loading = false;
        this.loadingSubjects[cacheKey].next(false);
        // En producción, considerar usar un servicio de logging en lugar de console.error
        // console.error(`Error cargando ${cacheKey}:`, error);
        // Retornar datos del cache si existen, aunque estén expirados
        if (this.cache[cacheKey]?.data.length > 0) {
          return of(this.cache[cacheKey].data);
        }
        return of([]);
      })
    );
  }

  /**
   * Obtiene el observable de datos para un tipo específico
   */
  getDataObservable(cacheKey: string): Observable<any[]> {
    if (!this.dataSubjects[cacheKey]) {
      this.dataSubjects[cacheKey] = new BehaviorSubject<any[]>([]);
    }
    return this.dataSubjects[cacheKey].asObservable();
  }

  /**
   * Obtiene el observable de estado de carga
   */
  getLoadingObservable(cacheKey: string): Observable<boolean> {
    if (!this.loadingSubjects[cacheKey]) {
      this.loadingSubjects[cacheKey] = new BehaviorSubject<boolean>(false);
    }
    return this.loadingSubjects[cacheKey].asObservable();
  }

  /**
   * Obtiene datos sincrónicamente desde cache (si están disponibles)
   */
  getCachedData(cacheKey: string): any[] {
    return this.cache[cacheKey]?.data || [];
  }

  /**
   * Invalida el cache de un tipo específico de datos
   */
  invalidateCache(cacheKey: string): void {
    if (this.cache[cacheKey]) {
      this.cache[cacheKey].timestamp = 0;
    }
    // También invalidar en el HttpCacheService
    const endpointMap: { [key: string]: string } = {
      'sucursales': 'sucursales/list',
      'formasDePago': 'formas-de-pago/list',
      'documentos': 'documentos/list',
      'documentosPorNombre': 'documentos/list-nombre',
      'usuarios': 'usuarios/list',
      'proyectos': 'proyectos/list',
      'categorias': 'categorias/list',
      'marcas': 'marcas/list',
      'clientes': 'clientes/list',
      'proveedores': 'proveedores/list',
      'canales': 'canales/list',
      'bodegas': 'bodegas/list'
    };

    const endpoint = endpointMap[cacheKey];
    if (endpoint) {
      this.cacheService.invalidatePattern(endpoint);
    }
  }

  /**
   * Limpia todo el cache
   */
  clearCache(): void {
    this.cache = {};
    Object.keys(this.dataSubjects).forEach(key => {
      this.dataSubjects[key].next([]);
    });
  }

  /**
   * Precarga los datos más comunes al iniciar la aplicación
   */
  preloadCommonData(): void {
    // Precargar datos comunes en segundo plano
    setTimeout(() => {
      this.getSucursales().subscribe();
      this.getFormasDePago().subscribe();
      this.getDocumentos().subscribe();
    }, 1000);
  }
}

