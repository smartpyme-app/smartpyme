import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { of } from 'rxjs';

import { ClienteVista360Component } from './cliente-vista-360.component';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FidelizacionService } from '@services/fidelizacion.service';

describe('ClienteVista360Component', () => {
  let component: ClienteVista360Component;
  let fixture: ComponentFixture<ClienteVista360Component>;
  let mockApiService: jasmine.SpyObj<ApiService>;
  let mockAlertService: jasmine.SpyObj<AlertService>;
  let mockFidelizacionService: jasmine.SpyObj<FidelizacionService>;
  let mockModalService: jasmine.SpyObj<BsModalService>;
  let mockRouter: jasmine.SpyObj<Router>;
  let mockActivatedRoute: any;

  beforeEach(async () => {
    const apiServiceSpy = jasmine.createSpyObj('ApiService', ['auth_user']);
    const alertServiceSpy = jasmine.createSpyObj('AlertService', ['showAlert']);
    const fidelizacionServiceSpy = jasmine.createSpyObj('FidelizacionService', ['getClienteDetalles']);
    const modalServiceSpy = jasmine.createSpyObj('BsModalService', ['show']);
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    mockActivatedRoute = {
      snapshot: {
        params: { id: '123' }
      }
    };

    await TestBed.configureTestingModule({
      declarations: [ClienteVista360Component],
      providers: [
        { provide: ApiService, useValue: apiServiceSpy },
        { provide: AlertService, useValue: alertServiceSpy },
        { provide: FidelizacionService, useValue: fidelizacionServiceSpy },
        { provide: BsModalService, useValue: modalServiceSpy },
        { provide: Router, useValue: routerSpy },
        { provide: ActivatedRoute, useValue: mockActivatedRoute }
      ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ClienteVista360Component);
    component = fixture.componentInstance;
    mockApiService = TestBed.inject(ApiService) as jasmine.SpyObj<ApiService>;
    mockAlertService = TestBed.inject(AlertService) as jasmine.SpyObj<AlertService>;
    mockFidelizacionService = TestBed.inject(FidelizacionService) as jasmine.SpyObj<FidelizacionService>;
    mockModalService = TestBed.inject(BsModalService) as jasmine.SpyObj<BsModalService>;
    mockRouter = TestBed.inject(Router) as jasmine.SpyObj<Router>;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize with default values', () => {
    expect(component.loading).toBeFalse();
    expect(component.activeTab).toBe('analytics');
    expect(component.activeHistoryTab).toBe('transactions');
    expect(component.showAddNoteModal).toBeFalse();
  });

  it('should load cliente data on init', () => {
    spyOn(component, 'loadCliente');
    component.ngOnInit();
    expect(component.loadCliente).toHaveBeenCalled();
  });

  it('should switch tabs correctly', () => {
    component.showTab('products');
    expect(component.activeTab).toBe('products');
    
    component.showTab('history');
    expect(component.activeTab).toBe('history');
  });

  it('should switch history tabs correctly', () => {
    component.showHistoryTab('visits');
    expect(component.activeHistoryTab).toBe('visits');
  });

  it('should open and close add note modal', () => {
    component.openAddNoteModal();
    expect(component.showAddNoteModal).toBeTrue();
    expect(component.newNote).toBeDefined();
    
    component.closeAddNoteModal();
    expect(component.showAddNoteModal).toBeFalse();
  });

  it('should format numbers correctly', () => {
    expect(component.formatNumber(1234)).toBe('1.234');
    expect(component.formatNumber(1234567)).toBe('1.234.567');
  });

  it('should get priority class correctly', () => {
    expect(component.getPriorityClass('high')).toBe('priority-tag high');
    expect(component.getPriorityClass('medium')).toBe('priority-tag medium');
    expect(component.getPriorityClass('low')).toBe('priority-tag medium'); // default
  });

  it('should get priority text correctly', () => {
    expect(component.getPriorityText('high')).toBe('Alta Prioridad');
    expect(component.getPriorityText('medium')).toBe('Información General');
    expect(component.getPriorityText('low')).toBe('Media'); // default
  });
});
