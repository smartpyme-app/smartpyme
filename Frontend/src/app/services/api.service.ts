/**
 * @deprecated Este servicio está siendo refactorizado.
 * Por favor, usa los servicios específicos:
 * - HttpService para llamadas HTTP
 * - AuthService para autenticación
 * - PermissionService para permisos y roles
 * - UtilityService para utilidades
 *
 * Este servicio se mantiene por compatibilidad hacia atrás y delega a los servicios específicos.
 */
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { HttpService } from '@services/http.service';
import { AuthService } from '@services/auth.service';
import { PermissionService } from '@services/permission.service';
import { UtilityService } from '@services/utility.service';
import { AlertService } from '@services/alert.service';
import { environment } from './../../environments/environment';
import { FuncionalidadesService } from '@services/functionalities.service';

export const GUARD_TYPES = {
  ADMIN: 'admin',
  CITAS: 'citas',
  SUPER_ADMIN: 'superAdmin',
} as const;

interface UserPermissions {
  role: string;
  rolePermissions: string[];
  directPermissions: string[];
  revokedPermissions: string[];
  effectivePermissions: string[];
}

@Injectable()
export class ApiService {
  /** Empresa 324: inventario operaciones solo Administrador. */
  private static readonly EMPRESA_ID_INVENTARIO_OPERACIONES_SOLO_ADMIN = 324;

  public appUrl: string = environment.APP_URL;
  public baseUrl: string = environment.API_URL;
  public apiUrl = this.baseUrl + '/api/';

  constructor(
    private httpService: HttpService,
    private authService: AuthService,
    private permissionService: PermissionService,
    private utilityService: UtilityService,
    private alertService: AlertService,
    private funcionalidadesService: FuncionalidadesService
  ) {}

  // ========== Métodos HTTP (delegados a HttpService) ==========
  getToUrl(url: string): Observable<any> {
    return this.httpService.getToUrl(url);
  }

  getAll(url: string, filtros: any = {}): Observable<any> {
    return this.httpService.getAll(url, filtros);
  }

  read(url: string, id: number): Observable<any> {
    return this.httpService.read(url, id);
  }

  filter(url: string, filter: any): Observable<any> {
    return this.httpService.filter(url, filter);
  }

  getAsText(url: string): Observable<string> {
    return this.httpService.getAsText(url);
  }

  get(url: string): Observable<any> {
    return this.httpService.get(url);
  }

  store(url: string, model: any): Observable<any> {
    return this.httpService.store(url, model);
  }

  storeWithTimeout(url: string, model: any, timeoutMs: number = 300000): Observable<any> {
    return this.httpService.storeWithTimeout(url, model, timeoutMs);
  }

  update(url: string, id: number, model: any): Observable<any> {
    return this.httpService.update(url, id, model);
  }

  delete(url: string, id: number): Observable<any> {
    return this.httpService.delete(url, id);
  }

  putToUrl(url: string, model: any): Observable<any> {
    return this.httpService.putToUrl(url, model);
  }

  // login(user:any) {return this.http.post<any>(this.apiUrl + 'login', user).pipe(map((response: HttpResponse<any>) => {let data:any = response; if (data.token && data.user) {localStorage.setItem('SP_token', JSON.stringify(data.token)); localStorage.setItem('SP_auth_user', JSON.stringify(data.user)); this.funcionalidadesService.limpiarCache(); this.loadConstants(); } }) ); }
  // register(user:any) {return this.http.post<any>(this.apiUrl + 'register', user).pipe(map((response: HttpResponse<any>) => {let data:any = response; if (data) {localStorage.setItem('SP_user_register', JSON.stringify(data)); } })); }

  paginate(url: string, filtros: any = {}): Observable<any> {
    return this.httpService.paginate(url, filtros);
  }

  upload(url: string, formData: any): Observable<any> {
    return this.httpService.upload(url, formData);
  }

  export(url: string, filtros: any, timeoutMs: number = 120000): Observable<Blob> {
    return this.httpService.export(url, filtros, timeoutMs);
  }

  /** Exportación Excel vía POST (cuerpo JSON), p. ej. listado con columnas calculadas en cliente. */
  exportPost(url: string, body: any): Observable<Blob> {
    return this.httpService.exportPost(url, body);
  }

  exportWithUrl(url: string, filtros: any): Observable<any> {
    return this.httpService.exportWithUrl(url, filtros);
  }

  exportAcumulado(url: string, filtros: any): Observable<Blob> {
    return this.httpService.exportAcumulado(url, filtros);
  }

  exportAcumuladoReportes(url: string, filtros: any): Observable<Blob> {
    return this.httpService.exportAcumuladoReportes(url, filtros);
  }

  download(url: string): Observable<Blob> {
    return this.httpService.download(url);
  }

  generatePayrollSlips(planillaId: number): Observable<Blob> {
    return this.httpService.generatePayrollSlips(planillaId);
  }

  generateIndividualPayrollSlip(detalleId: number): Observable<Blob> {
    return this.httpService.generateIndividualPayrollSlip(detalleId);
  }

  getUserData(userId: number): Observable<any> {
    return this.httpService.getUserData(userId);
  }

  getActividadesEconomicas(): Observable<any> {
    return this.httpService.getActividadesEconomicas();
  }

  getModules(): Observable<any> {
    return this.httpService.getModules();
  }

  // ========== Métodos de Autenticación (delegados a AuthService) ==========
  login(user: any): Observable<any> {
    return this.authService.login(user);
  }

  register(user: any): Observable<any> {
    return this.authService.register(user);
  }

  logout(): void {
    this.authService.logout();
  }

  autenticated(): boolean {
    return this.authService.autenticated();
  }

  auth_user(): any {
    return this.authService.auth_user();
  }

  register_user(): any {
    return this.authService.register_user();
  }

  auth_token(): string {
    return this.authService.auth_token();
  }

  loadUserPermissions(userId: number): void {
    this.permissionService.loadUserPermissions(userId);
  }

  // ========== Métodos de Utilidades (delegados a UtilityService) ==========
  downloadFile(blob: Blob, filename: string): void {
    this.utilityService.downloadFile(blob, filename);
  }

  saludar(): string {
    return this.utilityService.saludar();
  }

  date(): string {
    return this.utilityService.date();
  }

  datetime(): string {
    return this.utilityService.datetime();
  }

  dataURItoBlob(dataURI: any): Blob {
    return this.utilityService.dataURItoBlob(dataURI);
  }

  slug(str: any): string | undefined {
    return this.utilityService.slug(str);
  }

  toggleTheme(): void {
    this.utilityService.toggleTheme();
  }

  loadTheme(): void {
    this.utilityService.loadTheme();
  }

  generateGoogleCalendarLink(event: any): string {
    return this.utilityService.generateGoogleCalendarLink(event);
  }

  getPosition(): Promise<any> {
    return this.utilityService.getPosition();
  }

  loadData(): void {
    this.utilityService.loadData();
  }

  getConstants(): any {
    return this.utilityService.getConstants();
  }

  // ========== Métodos de Permisos (delegados a PermissionService) ==========
  hasPermission(permission: string): boolean {
    return this.permissionService.hasPermission(permission);
  }

  hasAnyPermission(permissions: string[]): boolean {
    return this.permissionService.hasAnyPermission(permissions);
  }

  canAccessModule(moduleName: string): boolean {
    return this.permissionService.canAccessModule(moduleName);
  }

  isAdmin(): boolean {
    return this.permissionService.isAdmin();
  }

  isAdminCreate(): boolean {
    return this.permissionService.isAdminCreate();
  }

  canCreate(): boolean {
    return this.permissionService.canCreate();
  }

  canEdit(): boolean {
    return this.permissionService.canEdit();
  }

  canDelete(): boolean {
    return this.permissionService.canDelete();
  }

  canChange(): boolean {
    return this.permissionService.canChange();
  }

  canCreateTest(permission: string): boolean {
    return this.permissionService.canCreateTest(permission);
  }

  canEditTest(permission: string): boolean {
    return this.permissionService.canEditTest(permission);
  }

  canDeleteTest(permission: string): boolean {
    return this.permissionService.canDeleteTest(permission);
  }

  verifyRoleAdmin(): boolean {
    return this.permissionService.verifyRoleAdmin();
  }

  isNotSuperAdmin(): boolean {
    return this.permissionService.isNotSuperAdmin();
  }

  isAdminRole(): boolean {
    return this.permissionService.isAdminRole();
  }

  verifyVentasRole(): boolean {
    return this.permissionService.verifyVentasRole();
  }

  verifyCitasRole(): boolean {
    return this.permissionService.verifyCitasRole();
  }

  validateRole(roleToCheck: string, equals: boolean = true): boolean {
    return this.permissionService.validateRole(roleToCheck, equals);
  }

  isSupervisorLimitado(): boolean {
    return this.permissionService.isSupervisorLimitado();
  }

  isVentasLimitado(): boolean {
    return this.permissionService.isVentasLimitado();
  }

  isVentas(): boolean {
    return this.permissionService.isVentas();
  }

  isLotesActivo(): boolean {
    return this.auth_user()?.empresa?.custom_empresa?.configuraciones?.lotes_activo ?? false;
  }

    /** Indica si el campo componente químico está habilitado para la empresa del usuario actual */
    isComponenteQuimicoHabilitado(): boolean {
        const empresa = this.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        const customConfig = typeof empresa.custom_empresa === 'string'
            ? JSON.parse(empresa.custom_empresa)
            : empresa.custom_empresa;
        return customConfig?.configuraciones?.componente_quimico_activo === true;
    }

    /** Indica si el módulo de bancos (cuentas bancarias) está activo para la empresa del usuario actual */
    isModuloBancos(): boolean {
        const empresa = this.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        const customConfig = typeof empresa.custom_empresa === 'string'
            ? JSON.parse(empresa.custom_empresa)
            : empresa.custom_empresa;
        return customConfig?.configuraciones?.modulo_bancos === true;
    }

    /** Categorías de gasto personalizadas, departamentos y áreas (configuración de empresa). */
    isGastosCategoriasPersonalizadasHabilitadas(): boolean {
        const empresa = this.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        const customConfig = typeof empresa.custom_empresa === 'string'
            ? JSON.parse(empresa.custom_empresa)
            : empresa.custom_empresa;
        return customConfig?.configuraciones?.gastos_categorias_personalizadas === true;
    }

    /** Sidebar Gastos: categorías, departamentos y áreas si hay contabilidad en el plan o categorías personalizadas en empresa. */
    mostrarMenuConfigGastos(contabilidadHabilitada: boolean): boolean {
        return contabilidadHabilitada || this.isGastosCategoriasPersonalizadasHabilitadas();
    }

    /** Indica si mostrar estado de cuenta del cliente en facturación está habilitado */
    isEstadoCuentaEnFacturacionHabilitado(): boolean {
        const empresa = this.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        const customConfig = typeof empresa.custom_empresa === 'string'
            ? JSON.parse(empresa.custom_empresa)
            : empresa.custom_empresa;
        return customConfig?.configuraciones?.estado_cuenta_en_facturacion === true;
    }

    /**
     * Vista del menú lateral cuando la funcionalidad "Restaurantes y pedidos" está activa:
     * restaurante | pedidos | ambos (por defecto ambos = comportamiento anterior).
     */
    getVistaModuloRestaurantePedidos(): 'restaurante' | 'pedidos' | 'ambos' {
        const empresa = this.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return 'ambos';
        }
        const customConfig = typeof empresa.custom_empresa === 'string'
            ? JSON.parse(empresa.custom_empresa)
            : empresa.custom_empresa;
        const v = customConfig?.configuraciones?.vista_modulo_restaurante_pedidos;
        if (v === 'restaurante' || v === 'pedidos' || v === 'ambos') {
            return v;
        }
        return 'ambos';
    }

    /** Código de barras correlativo automático (también si persiste sku_correlativo_automatico en datos antiguos). */
    isBarcodeCorrelativoAutomatico(): boolean {
        const empresa = this.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        const customConfig = typeof empresa.custom_empresa === 'string'
            ? JSON.parse(empresa.custom_empresa)
            : empresa.custom_empresa;
        const c = customConfig?.configuraciones;
        return c?.barcode_correlativo_automatico === true || c?.sku_correlativo_automatico === true;
    }

    /** Total de stock en listado de inventario según filtros (Mi cuenta → Inventario) */
    isInventarioSumarStockBusquedas(): boolean {
        const empresa = this.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        const customConfig = typeof empresa.custom_empresa === 'string'
            ? JSON.parse(empresa.custom_empresa)
            : empresa.custom_empresa;
        return customConfig?.configuraciones?.inventario_sumar_stock_busquedas === true;
    }

    /** Ventas / Ventas limitado pueden elegir vendedor en facturación (Mi cuenta → Facturación). */
    isVentasPuedeCambiarVendedorFacturacion(): boolean {
        const empresa = this.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        const customConfig = typeof empresa.custom_empresa === 'string'
            ? JSON.parse(empresa.custom_empresa)
            : empresa.custom_empresa;
        return customConfig?.configuraciones?.ventas_puede_cambiar_vendedor_facturacion === true;
    }

  esEmpresaInventarioOperacionesSoloAdministrador(): boolean {
    const id = this.auth_user()?.id_empresa;
    return Number(id) === ApiService.EMPRESA_ID_INVENTARIO_OPERACIONES_SOLO_ADMIN;
  }

  /**
   * Pestañas y guard de rutas: para empresa 324 solo Administrador; en el resto de empresas sin restricción extra.
   */
  canAccederOperacionesInventario(): boolean {
    if (!this.esEmpresaInventarioOperacionesSoloAdministrador()) {
      return true;
    }
    return this.auth_user()?.tipo === 'Administrador';
  }

  /**
   * Botones crear (ajuste, traslado, entrada/salida): empresa 324 solo Administrador;
   * otras empresas conservan la regla anterior (canCreate y no Supervisor en 324).
   */
  puedeCrearOperacionesInventarioEnUi(): boolean {
    const usuario = this.auth_user();
    if (!this.esEmpresaInventarioOperacionesSoloAdministrador()) {
      return this.canCreate();
    }
    return usuario?.tipo === 'Administrador';
  }

  /** Empresa activó bloquear facturar/editar/gestionar cotizaciones para vendedores (Mi cuenta). */
  empresaBloqueaCotizacionesVendedores(): boolean {
    const u = this.auth_user();
    const cfg = u?.empresa?.custom_empresa?.configuraciones as
      | { bloquear_cotizaciones_vendedores?: boolean }
      | undefined;
    return cfg?.bloquear_cotizaciones_vendedores === true;
  }

  /**
   * Bloqueos de UI/API extra (facturar cotización desde menú, editar, detalles, cambiar estado): rol Ventas + opción empresa.
   * El listado solo cotizaciones propias usa isVentas() y no depende de esta opción.
   */
  restriccionesCotizacionesVendedoresActivas(): boolean {
    return this.isVentas() && this.empresaBloqueaCotizacionesVendedores();
  }
}
