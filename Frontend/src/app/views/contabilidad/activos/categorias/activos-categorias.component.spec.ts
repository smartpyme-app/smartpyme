import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ActivosCategoriasComponent } from './activos-categorias.component';

describe('ActivosCategoriasComponent', () => {
  let component: ActivosCategoriasComponent;
  let fixture: ComponentFixture<ActivosCategoriasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ActivosCategoriasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ActivosCategoriasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
