import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import { seriesUrl } from '../../common/urls';
import type { SeriesCard as Card } from '../../common/api';
import Cover from './Cover';
import { t } from '../util';

/** A series in a grid: the cover, title, author, status and chapter count. */
export default class SeriesCard extends Component<{ series: Card }> {
  view(): Mithril.Children {
    const series = this.attrs.series;

    return (
      <Link className="AnthologyCard" href={seriesUrl(series)}>
        <span className="AnthologyCard-cover">
          <Cover url={series.coverUrl} title={series.title} />
          <span className={'AnthologyStatus AnthologyStatus--' + series.status}>{t('status.' + series.status)}</span>
        </span>
        <span className="AnthologyCard-body">
          <span className="AnthologyCard-title">{series.title}</span>
          {series.author ? <span className="AnthologyCard-author">{t('card.by', { author: series.author.displayName })}</span> : null}
          <span className="AnthologyCard-meta">
            {t('card.chapters', { count: series.chapterCount })}
            {series.followerCount ? <> · {t('card.followers', { count: series.followerCount })}</> : null}
          </span>
        </span>
      </Link>
    );
  }
}
