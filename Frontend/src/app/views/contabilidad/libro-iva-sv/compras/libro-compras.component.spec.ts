import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { LibroComprasComponent } from './libro-compras.component';

describe('LibroComprasComponent', () => {
  let component: LibroComprasComponent;
  let fixture: ComponentFixture<LibroComprasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ LibroComprasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(LibroComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
