import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import { t } from '../util';

/** "4 of 15 chapters read" over a bar. */
export default class ProgressBar extends Component<{ done: number; total: number }> {
  view(): Mithril.Children {
    const { done, total } = this.attrs;
    const percent = total ? Math.round((done / total) * 100) : 0;

    return (
      <div className="AnthologyProgress">
        <div className="AnthologyProgress-bar" role="progressbar" aria-valuemin="0" aria-valuemax={total} aria-valuenow={done}>
          <span style={{ width: percent + '%' }} />
        </div>
        <div className="AnthologyProgress-text">{t('series.progress', { done, total })}</div>
      </div>
    );
  }
}
