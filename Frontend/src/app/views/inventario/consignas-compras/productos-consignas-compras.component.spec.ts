import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { BsModalService } from 'ngx-bootstrap/modal';

import { ProductosConsignasComprasComponent } from './productos-consignas-compras.component';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

describe('ProductosConsignasComprasComponent', () => {
  let component: ProductosConsignasComprasComponent;
  let fixture: ComponentFixture<ProductosConsignasComprasComponent>;

  beforeEach(async () => {
    const apiServiceSpy = jasmine.createSpyObj('ApiService', ['getAll', 'export', 'canEdit']);
    apiServiceSpy.getAll.and.returnValue(of([]));
    apiServiceSpy.canEdit.and.returnValue(true);

    await TestBed.configureTestingModule({
      declarations: [ProductosConsignasComprasComponent],
      providers: [
        { provide: ApiService, useValue: apiServiceSpy },
        { provide: AlertService, useValue: jasmine.createSpyObj('AlertService', ['error']) },
        { provide: BsModalService, useValue: jasmine.createSpyObj('BsModalService', ['show']) },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ProductosConsignasComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
