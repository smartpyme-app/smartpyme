import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { MateriaPrimaInformacionComponent } from './materia-prima-informacion.component';

describe('MateriaPrimaInformacionComponent', () => {
  let component: MateriaPrimaInformacionComponent;
  let fixture: ComponentFixture<MateriaPrimaInformacionComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ MateriaPrimaInformacionComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(MateriaPrimaInformacionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
