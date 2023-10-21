import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { LibroIvaComponent } from './libro-iva.component';

describe('LibroIvaComponent', () => {
  let component: LibroIvaComponent;
  let fixture: ComponentFixture<LibroIvaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ LibroIvaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(LibroIvaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
