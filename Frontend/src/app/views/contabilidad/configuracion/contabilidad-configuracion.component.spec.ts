import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ContabilidadConfiguracionComponent } from './contabilidad-configuracion.component';

describe('ContabilidadConfiguracionComponent', () => {
  let component: ContabilidadConfiguracionComponent;
  let fixture: ComponentFixture<ContabilidadConfiguracionComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ContabilidadConfiguracionComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ContabilidadConfiguracionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
