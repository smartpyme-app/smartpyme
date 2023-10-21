import { TestBed, async } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { VentasComponent } from './ventas.component';

describe('VentasComponent', () => {
  beforeEach(async(() => {
    TestBed.configureTestingModule({
      imports: [
        RouterTestingModule
      ],
      declarations: [
        VentasComponent
      ],
    }).compileComponents();
  }));

  it('should create the app', () => {
    const fixture = TestBed.createComponent(VentasComponent);
    const app = fixture.debugElement.componentInstance;
    expect(app).toBeTruthy();
  });

  it(`should have as title 'wproject'`, () => {
    const fixture = TestBed.createComponent(VentasComponent);
    const app = fixture.debugElement.componentInstance;
    expect(app.title).toEqual('wproject');
  });

  it('should render title in a h1 tag', () => {
    const fixture = TestBed.createComponent(VentasComponent);
    fixture.detectChanges();
    const compiled = fixture.debugElement.nativeElement;
    expect(compiled.querySelector('h1').textContent).toContain('Welcome to wproject!');
  });
});
