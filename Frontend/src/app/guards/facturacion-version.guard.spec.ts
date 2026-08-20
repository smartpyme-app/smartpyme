import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot } from '@angular/router';
import { FacturacionVersionGuard } from './facturacion-version.guard';
import { ApiService } from '@services/api.service';

describe('FacturacionVersionGuard', () => {
  let guard: FacturacionVersionGuard;
  let router: jasmine.SpyObj<Router>;
  let apiService: jasmine.SpyObj<ApiService>;

  const route = { queryParams: { mesa: '1' } } as unknown as ActivatedRouteSnapshot;
  const state = { url: '/venta/crear' } as unknown as RouterStateSnapshot;

  beforeEach(() => {
    router = jasmine.createSpyObj('Router', ['navigate', 'getCurrentNavigation']);
    router.getCurrentNavigation.and.returnValue({ extras: { state: { fromRestaurante: true } } } as any);
    apiService = jasmine.createSpyObj('ApiService', ['auth_user']);

    TestBed.configureTestingModule({
      providers: [
        FacturacionVersionGuard,
        { provide: Router, useValue: router },
        { provide: ApiService, useValue: apiService },
      ],
    });

    guard = TestBed.inject(FacturacionVersionGuard);
  });

  it('redirige a ventas-pos/crear cuando version_facturacion es pos', () => {
    apiService.auth_user.and.returnValue({
      empresa: { custom_empresa: { configuraciones: { version_facturacion: 'pos' } } },
    } as any);

    expect(guard.canActivate(route, state)).toBe(false);
    expect(router.navigate).toHaveBeenCalledWith(
      ['/ventas-pos/crear'],
      jasmine.objectContaining({
        queryParams: { mesa: '1' },
        state: { fromRestaurante: true },
      })
    );
  });

  it('redirige a ventas-v2/crear cuando version_facturacion es v2', () => {
    apiService.auth_user.and.returnValue({
      empresa: { custom_empresa: { configuraciones: { version_facturacion: 'v2' } } },
    } as any);

    expect(guard.canActivate(route, state)).toBe(false);
    expect(router.navigate).toHaveBeenCalledWith(
      ['/ventas-v2/crear'],
      jasmine.objectContaining({
        queryParams: { mesa: '1' },
        state: { fromRestaurante: true },
      })
    );
  });

  it('permite venta/crear cuando version_facturacion es original', () => {
    apiService.auth_user.and.returnValue({
      empresa: { custom_empresa: { configuraciones: { version_facturacion: 'original' } } },
    } as any);

    expect(guard.canActivate(route, state)).toBe(true);
    expect(router.navigate).not.toHaveBeenCalled();
  });
});
