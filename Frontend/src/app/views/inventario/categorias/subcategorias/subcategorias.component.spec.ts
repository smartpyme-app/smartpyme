import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { SubCategoriasComponent } from './subcategorias.component';

describe('SubCategoriasComponent', () => {
  let component: SubCategoriasComponent;
  let fixture: ComponentFixture<SubCategoriasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ SubCategoriasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(SubCategoriasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
