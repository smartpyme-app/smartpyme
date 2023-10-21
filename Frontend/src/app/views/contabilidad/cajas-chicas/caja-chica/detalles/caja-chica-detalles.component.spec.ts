import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajaChicaDetallesComponent } from './caja-chica-detalles.component';

describe('CajaChicaDetallesComponent', () => {
  let component: CajaChicaDetallesComponent;
  let fixture: ComponentFixture<CajaChicaDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajaChicaDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajaChicaDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
