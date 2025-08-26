import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { InventarioSalidaComponent } from './inventario-salida.component';

describe('InventarioSalidaComponent', () => {
  let component: InventarioSalidaComponent;
  let fixture: ComponentFixture<InventarioSalidaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ InventarioSalidaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(InventarioSalidaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
