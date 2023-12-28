import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FacturacionConsignaComponent } from './facturacion-consigna.component';

describe('FacturacionConsignaComponent', () => {
  let component: FacturacionConsignaComponent;
  let fixture: ComponentFixture<FacturacionConsignaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FacturacionConsignaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FacturacionConsignaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
