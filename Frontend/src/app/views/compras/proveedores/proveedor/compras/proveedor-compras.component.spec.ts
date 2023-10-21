import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProveedorComprasComponent } from './proveedor-compras.component';

describe('ProveedorComprasComponent', () => {
  let component: ProveedorComprasComponent;
  let fixture: ComponentFixture<ProveedorComprasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProveedorComprasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProveedorComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
