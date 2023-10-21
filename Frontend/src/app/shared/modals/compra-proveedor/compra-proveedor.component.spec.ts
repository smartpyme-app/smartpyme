import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CompraProveedorComponent } from './compra-proveedor.component';

describe('CompraProveedorComponent', () => {
  let component: CompraProveedorComponent;
  let fixture: ComponentFixture<CompraProveedorComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CompraProveedorComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CompraProveedorComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
