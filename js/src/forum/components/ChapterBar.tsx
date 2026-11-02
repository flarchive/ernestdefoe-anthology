import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import LinkButton from 'flarum/common/components/LinkButton';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { chapterUrl, seriesUrl } from '../../common/urls';
import type { DiscussionSeries } from '../../common/api';
import { t } from '../util';

interface Attrs {
  series: DiscussionSeries;
}

/** Under a chapter's title: the series, "Chapter 3 of 15", previous, all chapters, next. */
export class ChapterBar extends Component<Attrs> {
  view(): Mithril.Children {
    const series = this.attrs.series;
    const step = (to: DiscussionSeries['prev'], icon: string, label: string) =>
      to ? (
        <Link className="AnthologyBar-step" href={chapterUrl(to)} title={to.title} aria-label={extractText(t(label)) + ': ' + to.title}>
          <Icon name={icon} />
        </Link>
      ) : (
        <span className="AnthologyBar-step disabled" aria-hidden="true">
          <Icon name={icon} />
        </span>
      );

    return (
      <div className="AnthologyBar">
        <Link className="AnthologyBar-series" href={seriesUrl(series)} title={extractText(t('chapter_bar.contents'))}>
          {series.coverUrl ? (
            <img className="AnthologyBar-cover" src={series.coverUrl} alt="" loading="lazy" />
          ) : (
            <Icon name="fas fa-book-bookmark" />
          )}
          <span className="AnthologyBar-text">
            <span className="AnthologyBar-label">{t('chapter_bar.label', { number: series.number, count: series.count })}</span>
            <span className="AnthologyBar-title">{series.title}</span>
          </span>
        </Link>
        <span className="AnthologyBar-nav">
          {step(series.prev, 'fas fa-chevron-left', 'chapter_bar.previous')}
          <Link
            className="AnthologyBar-step"
            href={seriesUrl(series)}
            title={extractText(t('chapter_bar.contents'))}
            aria-label={extractText(t('chapter_bar.contents'))}
          >
            <Icon name="fas fa-list-ol" />
          </Link>
          {step(series.next, 'fas fa-chevron-right', 'chapter_bar.next')}
        </span>
      </div>
    );
  }
}

/** After the chapter's text: previous, all chapters and the next chapter by name. */
export class NextChapter extends Component<Attrs> {
  view(): Mithril.Children {
    const series = this.attrs.series;

    return (
      <nav className="AnthologyNext" aria-label={series.title}>
        <div className="AnthologyNext-meta">
          {t('chapter_bar.label', { number: series.number, count: series.count })} · <Link href={seriesUrl(series)}>{series.title}</Link>
        </div>
        <div className="AnthologyNext-actions">
          {series.prev ? (
            <LinkButton className="Button" icon="fas fa-chevron-left" href={chapterUrl(series.prev)} title={series.prev.title}>
              {t('chapter_bar.previous')}
            </LinkButton>
          ) : null}
          <LinkButton className="Button" icon="fas fa-list-ol" href={seriesUrl(series)}>
            {t('chapter_bar.contents')}
          </LinkButton>
          {series.next ? (
            <LinkButton className="Button Button--primary AnthologyNext-next" href={chapterUrl(series.next)} title={series.next.title}>
              <span className="AnthologyNext-nextLabel">{t('chapter_bar.next')}:</span>{' '}
              <span className="AnthologyNext-nextTitle">{series.next.title}</span> <Icon name="fas fa-chevron-right" />
            </LinkButton>
          ) : null}
        </div>
      </nav>
    );
  }
}
