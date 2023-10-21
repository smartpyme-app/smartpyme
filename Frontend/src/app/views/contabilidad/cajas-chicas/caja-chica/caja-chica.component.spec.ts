import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajaChicaComponent } from './caja-chica.component';

describe('CajaChicaComponent', () => {
  let component: CajaChicaComponent;
  let fixture: ComponentFixture<CajaChicaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajaChicaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajaChicaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
