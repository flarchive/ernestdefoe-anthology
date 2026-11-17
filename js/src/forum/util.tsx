import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

/** The admin's word for a series: series, novel, story or book. */
export const noun = () => (app.forum.attribute('anthologyNoun') as string) || 'series';

/** A forum string, with the admin's noun and its "a …" / "this …" forms always available. */
export function t(key: string, params: Record<string, unknown> = {}): any {
  const base = 'ernestdefoe-anthology.forum.';
  const n = noun();
  return app.translator.trans(base + key, {
    noun: n,
    a_noun: extractText(app.translator.trans(base + 'a_noun', { noun: n })),
    the_noun: extractText(app.translator.trans(base + 'the_noun', { noun: n })),
    ...params,
  });
}

/** Shows an API error the way Flarum does, or a generic one. */
export function failed(error: any): void {
  if (!error?.alert) app.alerts.show({ type: 'error' }, t('errors.generic'));
}
