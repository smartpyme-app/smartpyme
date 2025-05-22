import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { LibroAnuladosComponent } from './libro-anulados.component';

describe('LibroAnuladosComponent', () => {
  let component: LibroAnuladosComponent;
  let fixture: ComponentFixture<LibroAnuladosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ LibroAnuladosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(LibroAnuladosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
