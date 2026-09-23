import { Component, signal } from '@angular/core';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { environment } from '../../../../environments/environment';
import { ComposedPrompt, Playbook } from '../../../core/models/playbook.model';
import { PlaybookApplication, PlaybookPickerComponent } from './playbook-picker.component';

const gitConventions: Playbook = {
  id: 'builtin:git-conventions',
  name: 'Git conventions',
  description: null,
  kind: 'rule',
  appliesTo: 'change',
  body: 'Conventional Commits.',
  default: true,
  parameters: [],
  engine: null,
  model: null,
  builtIn: true,
};

const minimalDiff: Playbook = {
  ...gitConventions,
  id: 'r2',
  name: 'Minimal diff',
  default: false,
  builtIn: false,
};

const upgrade: Playbook = {
  id: 't1',
  name: 'Upgrade',
  description: null,
  kind: 'task',
  appliesTo: 'change',
  body: 'Upgrade {{package}}.',
  default: false,
  parameters: [{ name: 'package', label: 'Package', default: 'acme/lib', required: true }],
  engine: 'kiro',
  model: null,
  builtIn: false,
};

@Component({
  standalone: true,
  imports: [PlaybookPickerComponent],
  template: `<app-playbook-picker
    appliesTo="change"
    [rules]="rules()"
    [prompt]="prompt()"
    (applied)="onApplied($event)"
  />`,
})
class HostComponent {
  readonly rules = signal('');
  readonly prompt = signal('');
  readonly applications: PlaybookApplication[] = [];

  onApplied(application: PlaybookApplication): void {
    this.applications.push(application);
    if (undefined !== application.rules) {
      this.rules.set(application.rules);
    }
    if (undefined !== application.prompt) {
      this.prompt.set(application.prompt);
    }
  }
}

describe('PlaybookPickerComponent', () => {
  let fixture: ComponentFixture<HostComponent>;
  let host: HostComponent;
  let httpMock: HttpTestingController;

  const picker = (): PlaybookPickerComponent =>
    fixture.debugElement.children[0].componentInstance as PlaybookPickerComponent;

  const composed = (overrides: Partial<ComposedPrompt> = {}): ComposedPrompt => ({
    rules: '### Git conventions\n\nConventional Commits.',
    prompt: null,
    engine: null,
    model: null,
    sources: [
      { id: 'builtin:git-conventions', name: 'Git conventions', kind: 'rule', builtIn: true },
    ],
    ...overrides,
  });

  /** The picker debounces selection changes before composing. */
  const flushCompose = async (response: ComposedPrompt): Promise<void> => {
    await vi.advanceTimersByTimeAsync(200);
    httpMock.expectOne(`${environment.apiUrl}/playbooks/compose`).flush(response);
    fixture.detectChanges();
  };

  beforeEach(async () => {
    vi.useFakeTimers();
    await TestBed.configureTestingModule({
      imports: [HostComponent],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(HostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
    httpMock
      .expectOne(`${environment.apiUrl}/playbooks?appliesTo=change`)
      .flush({ playbooks: [gitConventions, minimalDiff, upgrade] });
    fixture.detectChanges();
  });

  afterEach(() => {
    httpMock.verify();
    vi.useRealTimers();
  });

  it('pre-selects the default rules and composes them into the empty rules field', async () => {
    expect(picker().isRuleSelected('builtin:git-conventions')).toBe(true);
    expect(picker().isRuleSelected('r2')).toBe(false);

    await flushCompose(composed());

    expect(host.rules()).toBe('### Git conventions\n\nConventional Commits.');
    expect(host.prompt()).toBe('');
    expect(host.applications.at(-1)?.sources.map(source => source.id)).toEqual([
      'builtin:git-conventions',
    ]);
  });

  it('a task fills the prompt with its rendered text and suggests its engine', async () => {
    await flushCompose(composed());

    picker().selectTask('t1');
    fixture.detectChanges();
    await vi.advanceTimersByTimeAsync(200);
    const request = httpMock.expectOne(`${environment.apiUrl}/playbooks/compose`);
    expect(request.request.body).toEqual({
      appliesTo: 'change',
      ruleIds: ['builtin:git-conventions'],
      taskId: 't1',
      parameters: { package: 'acme/lib' },
    });
    request.flush(composed({ prompt: 'Upgrade acme/lib.', engine: 'kiro' }));
    fixture.detectChanges();

    expect(host.prompt()).toBe('Upgrade acme/lib.');
    expect(host.applications.at(-1)?.engine).toBe('kiro');
  });

  it('never overwrites a field the user edited by hand until asked to', async () => {
    await flushCompose(composed());

    host.rules.set('### Git conventions\n\nConventional Commits, but in Polish.');
    fixture.detectChanges();
    expect(picker()['rulesEdited']()).toBe(true);

    picker().toggleRule('r2');
    await flushCompose(composed({ rules: 'two rules' }));
    expect(host.rules()).toBe('### Git conventions\n\nConventional Commits, but in Polish.');
    expect(host.applications.at(-1)?.rules).toBeUndefined();

    picker().replaceEdited();
    await flushCompose(composed({ rules: 'two rules' }));
    expect(host.rules()).toBe('two rules');
    expect(picker()['rulesEdited']()).toBe(false);
  });

  it('treats a field that was already filled before the first composition as hand-written', () => {
    host.rules.set('My own rules');
    fixture.detectChanges();

    expect(picker()['rulesEdited']()).toBe(true);
  });
});
