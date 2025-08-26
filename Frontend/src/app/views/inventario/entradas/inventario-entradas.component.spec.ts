import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { InventarioEntradasComponent } from './inventario-entradas.component';

describe('InventarioEntradasComponent', () => {
  let component: InventarioEntradasComponent;
  let fixture: ComponentFixture<InventarioEntradasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ InventarioEntradasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(InventarioEntradasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
