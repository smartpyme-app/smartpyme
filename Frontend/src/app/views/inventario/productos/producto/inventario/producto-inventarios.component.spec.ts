import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoInventariosComponent } from './producto-inventarios.component';

describe('ProductoInventariosComponent', () => {
  let component: ProductoInventariosComponent;
  let fixture: ComponentFixture<ProductoInventariosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoInventariosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoInventariosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
