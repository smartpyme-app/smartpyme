import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CuentasCobrarComponent } from './cuentas-cobrar.component';

describe('CuentasCobrarComponent', () => {
  let component: CuentasCobrarComponent;
  let fixture: ComponentFixture<CuentasCobrarComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CuentasCobrarComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CuentasCobrarComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
