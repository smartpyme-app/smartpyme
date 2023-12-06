import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FacturacionCompraComponent } from './facturacion-compra.component';

describe('FacturacionCompraComponent', () => {
  let component: FacturacionCompraComponent;
  let fixture: ComponentFixture<FacturacionCompraComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FacturacionCompraComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FacturacionCompraComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
