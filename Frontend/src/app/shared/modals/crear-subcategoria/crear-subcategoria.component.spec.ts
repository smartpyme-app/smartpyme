import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearSubCategoriaComponent } from './crear-subcategoria.component';

describe('CrearCategoriaActivoComponent', () => {
  let component: CrearSubCategoriaComponent;
  let fixture: ComponentFixture<CrearSubCategoriaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CrearSubCategoriaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CrearSubCategoriaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
