import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FacturacionV2Component } from './facturacion-v2.component';

describe('FacturacionV2Component', () => {
  let component: FacturacionV2Component;
  let fixture: ComponentFixture<FacturacionV2Component>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FacturacionV2Component ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FacturacionV2Component);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});

