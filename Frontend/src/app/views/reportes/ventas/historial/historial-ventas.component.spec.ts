import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { HistorialVentasComponent } from './historial-ventas.component';

describe('HistorialVentasComponent', () => {
  let component: HistorialVentasComponent;
  let fixture: ComponentFixture<HistorialVentasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ HistorialVentasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(HistorialVentasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
