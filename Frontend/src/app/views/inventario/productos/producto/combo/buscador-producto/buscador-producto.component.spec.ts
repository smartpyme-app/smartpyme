import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { BuscadorProductoComponent } from './buscador-producto.component';

describe('CompraProductoComponent', () => {
  let component: BuscadorProductoComponent;
  let fixture: ComponentFixture<BuscadorProductoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ BuscadorProductoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(BuscadorProductoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
