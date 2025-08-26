import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { InventarioEntradaComponent } from './inventario-entrada.component';

describe('InventarioEntradaComponent', () => {
  let component: InventarioEntradaComponent;
  let fixture: ComponentFixture<InventarioEntradaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ InventarioEntradaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(InventarioEntradaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
