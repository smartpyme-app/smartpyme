import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajaEstadisticasComponent } from './caja-estadisticas.component';

describe('CajaEstadisticasComponent', () => {
  let component: CajaEstadisticasComponent;
  let fixture: ComponentFixture<CajaEstadisticasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajaEstadisticasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajaEstadisticasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
