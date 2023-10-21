import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CuentasPagarComponent } from './cuentas-pagar.component';

describe('CuentasPagarComponent', () => {
  let component: CuentasPagarComponent;
  let fixture: ComponentFixture<CuentasPagarComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CuentasPagarComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CuentasPagarComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
