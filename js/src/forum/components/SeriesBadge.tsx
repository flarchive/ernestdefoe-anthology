import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import Tooltip from 'flarum/common/components/Tooltip';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type { Ref } from '../../common/api';
import { seriesUrl } from '../../common/urls';
import { t } from '../util';

interface Attrs extends ComponentAttrs {
  series: Ref;
}

/**
 * The small book beside a chapter's tags in the discussion list, opening its series. The list item is
 * already one link, so this can't be another <a>: it acts as one (click, Enter) without nesting.
 */
export default class SeriesBadge extends Component<Attrs> {
  view(): Mithril.Children {
    const series = this.attrs.series;
    const label = extractText(t('list_badge', { title: series.title }));
    const open = (e: Event) => {
      e.preventDefault();
      e.stopPropagation();
      m.route.set(seriesUrl(series));
    };

    return (
      <Tooltip text={label}>
        <span
          className="AnthologyBadge"
          role="link"
          tabindex="0"
          aria-label={label}
          onclick={open}
          onkeydown={(e: KeyboardEvent) => {
            if (e.key === 'Enter') open(e);
          }}
        >
          <Icon name="fas fa-book-bookmark" />
        </span>
      </Tooltip>
    );
  }
}
