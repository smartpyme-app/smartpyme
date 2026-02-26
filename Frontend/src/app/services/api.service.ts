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
import { ChatService } from '@services/chat/chat.service';
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

  // login(user:any) {return this.http.post<any>(this.apiUrl + 'login', user).pipe(map((response: HttpResponse<any>) => {let data:any = response; if (data.token && data.user) {localStorage.setItem('SP_token', JSON.stringify(data.token)); localStorage.setItem('SP_auth_user', JSON.stringify(data.user)); this.funcionalidadesService.limpiarCache(); this.loadConstants(); } }) ); }
  // register(user:any) {return this.http.post<any>(this.apiUrl + 'register', user).pipe(map((response: HttpResponse<any>) => {let data:any = response; if (data) {localStorage.setItem('SP_user_register', JSON.stringify(data)); } })); }

  paginate(url: string, filtros: any = {}): Observable<any> {
    return this.httpService.paginate(url, filtros);
  }

  upload(url: string, formData: any): Observable<any> {
    return this.httpService.upload(url, formData);
  }

  export(url: string, filtros: any): Observable<Blob> {
    return this.httpService.export(url, filtros);
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
}
