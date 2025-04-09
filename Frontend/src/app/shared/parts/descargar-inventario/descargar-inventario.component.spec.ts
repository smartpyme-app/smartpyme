import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DescargarInventarioComponent } from './descargar-inventario.component';

describe('DescargarInventarioComponent', () => {
  let component: DescargarInventarioComponent;
  let fixture: ComponentFixture<DescargarInventarioComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DescargarInventarioComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DescargarInventarioComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
