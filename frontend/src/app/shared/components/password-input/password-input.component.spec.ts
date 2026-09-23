import { ComponentFixture, TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PasswordInputComponent } from './password-input.component';

describe('PasswordInputComponent', () => {
  let fixture: ComponentFixture<PasswordInputComponent>;
  let component: PasswordInputComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PasswordInputComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PasswordInputComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('renders the input as type password by default', () => {
    const input: HTMLInputElement = fixture.nativeElement.querySelector('input');

    expect(input.type).toBe('password');
  });

  it('toggles the input type to text when the visibility button is clicked', () => {
    const button: HTMLButtonElement = fixture.nativeElement.querySelector('button');

    button.click();
    fixture.detectChanges();

    const input: HTMLInputElement = fixture.nativeElement.querySelector('input');
    expect(input.type).toBe('text');
  });

  it('propagates typed values through the ControlValueAccessor onChange callback', () => {
    const onChange = vi.fn();
    component.registerOnChange(onChange);

    component['_onInputChange']('hunter2');

    expect(onChange).toHaveBeenCalledWith('hunter2');
  });

  it('writeValue updates the displayed value', async () => {
    component.writeValue('preset-password');
    fixture.detectChanges();
    // NgModel applies the DefaultValueAccessor write in a microtask (to avoid an
    // ExpressionChangedAfterItHasBeenCheckedError), so the DOM isn't updated yet
    // right after detectChanges() returns.
    await fixture.whenStable();

    const input: HTMLInputElement = fixture.nativeElement.querySelector('input');
    expect(input.value).toBe('preset-password');
  });
});
