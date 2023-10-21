import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { BuscadorProductosComponent } from './buscador-productos.component';

describe('BuscadorProductosComponent', () => {
  let component: BuscadorProductosComponent;
  let fixture: ComponentFixture<BuscadorProductosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ BuscadorProductosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(BuscadorProductosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
