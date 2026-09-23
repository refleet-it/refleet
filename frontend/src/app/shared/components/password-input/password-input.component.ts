import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideEye, lucideEyeOff } from '@ng-icons/lucide';
import {
  booleanAttribute,
  ChangeDetectionStrategy,
  Component,
  forwardRef,
  input,
  signal,
  ViewEncapsulation,
} from '@angular/core';
import { ControlValueAccessor, FormsModule, NG_VALUE_ACCESSOR } from '@angular/forms';
import { HlmInput } from '@spartan-ng/helm/input';

/**
 * Password input with a show/hide toggle, replacing PrimeNG's `p-password`.
 * Implements `ControlValueAccessor` so it can be used with `formControlName`
 * exactly like a native input.
 */
@Component({
  selector: 'app-password-input',
  standalone: true,
  imports: [FormsModule, HlmInput, NgIcon],
  providers: [
    provideIcons({ lucideEye, lucideEyeOff }),
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => PasswordInputComponent),
      multi: true,
    },
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  encapsulation: ViewEncapsulation.None,
  host: { class: 'relative block w-full' },
  template: `
    <input
      hlmInput
      class="w-full pe-9"
      [id]="inputId()"
      [type]="_visible() ? 'text' : 'password'"
      [placeholder]="placeholder()"
      [autocomplete]="autocomplete()"
      [attr.data-testid]="testId() || null"
      [ngModel]="_value()"
      [forceInvalid]="forceInvalid()"
      (ngModelChange)="_onInputChange($event)"
      (blur)="_onTouched?.()"
    />
    <button
      type="button"
      class="text-muted-foreground hover:text-foreground absolute inset-y-0 end-0 flex w-9 items-center justify-center"
      tabindex="-1"
      [attr.aria-label]="_visible() ? hidePasswordLabel() : showPasswordLabel()"
      (click)="toggleVisibility()"
    >
      <ng-icon [name]="_visible() ? 'lucideEyeOff' : 'lucideEye'" size="1rem" />
    </button>
  `,
})
export class PasswordInputComponent implements ControlValueAccessor {
  public readonly inputId = input<string>('');
  public readonly placeholder = input<string>('');
  public readonly autocomplete = input<string>('current-password');
  public readonly testId = input<string>('');
  public readonly showPasswordLabel = input<string>('Show password');
  public readonly hidePasswordLabel = input<string>('Hide password');
  /** Forces the invalid (red) state, since the host's formControlName status can't reach the inner native input. */
  public readonly forceInvalid = input(false, { transform: booleanAttribute });

  protected readonly _visible = signal(false);
  protected readonly _value = signal('');
  protected _disabled = signal(false);

  private _onChange?: (value: string) => void;
  protected _onTouched?: () => void;

  toggleVisibility(): void {
    this._visible.update(v => !v);
  }

  protected _onInputChange(value: string): void {
    this._value.set(value);
    this._onChange?.(value);
  }

  writeValue(value: string): void {
    this._value.set(value ?? '');
  }

  registerOnChange(fn: (value: string) => void): void {
    this._onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this._onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this._disabled.set(isDisabled);
  }
}
