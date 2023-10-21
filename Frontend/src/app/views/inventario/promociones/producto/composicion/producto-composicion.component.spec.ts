import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoComposicionComponent } from './producto-composicion.component';

describe('ProductoComposicionComponent', () => {
  let component: ProductoComposicionComponent;
  let fixture: ComponentFixture<ProductoComposicionComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoComposicionComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoComposicionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
