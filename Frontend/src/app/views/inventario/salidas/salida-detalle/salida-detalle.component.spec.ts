import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SalidaDetalleComponent } from './salida-detalle.component';

describe('SalidaDetalleComponent', () => {
  let component: SalidaDetalleComponent;
  let fixture: ComponentFixture<SalidaDetalleComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ SalidaDetalleComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(SalidaDetalleComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
}); 