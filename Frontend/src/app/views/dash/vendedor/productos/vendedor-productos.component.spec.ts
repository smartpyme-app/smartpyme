import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { VendedorProductosComponent } from './vendedor-productos.component';

describe('VendedorProductosComponent', () => {
  let component: VendedorProductosComponent;
  let fixture: ComponentFixture<VendedorProductosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ VendedorProductosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(VendedorProductosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
