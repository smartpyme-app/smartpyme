import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { InventarioSalidasComponent } from './inventario-salidas.component';

describe('InventarioSalidasComponent', () => {
  let component: InventarioSalidasComponent;
  let fixture: ComponentFixture<InventarioSalidasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ InventarioSalidasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(InventarioSalidasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
