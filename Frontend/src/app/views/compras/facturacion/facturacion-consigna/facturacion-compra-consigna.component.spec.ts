import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FacturacionCompraConsignaComponent } from './facturacion-compra-consigna.component';

describe('FacturacionCompraConsignaComponent', () => {
  let component: FacturacionCompraConsignaComponent;
  let fixture: ComponentFixture<FacturacionCompraConsignaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FacturacionCompraConsignaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FacturacionCompraConsignaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
