import { Injectable, signal } from '@angular/core';

@Injectable({
  providedIn: 'root',
})
export class ToastPositionService {
  private static readonly DESKTOP_BREAKPOINT_PX = 768;

  position = signal<'top-center' | 'bottom-right'>(this.getPosition());

  constructor() {
    if (typeof window !== 'undefined') {
      window.addEventListener('resize', () => this.position.set(this.getPosition()));
    }
  }

  private getPosition(): 'top-center' | 'bottom-right' {
    return typeof window !== 'undefined' &&
      window.innerWidth >= ToastPositionService.DESKTOP_BREAKPOINT_PX
      ? 'bottom-right'
      : 'top-center';
  }
}
