import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';
import { deleteSeries, follow, removeChapter, reorder, showSeries } from '../../common/api';
import { chapterUrl, routeId, seriesUrl } from '../../common/urls';
import type { ChapterItem, Series, SeriesCard } from '../../common/api';
import Cover from '../components/Cover';
import ProgressBar from '../components/ProgressBar';
import { failed, t } from '../util';

/**
 * /series/:id — the cover, what it's about and the table of contents, with the
 * reader's progress and Follow on the side. The owner reorders and removes
 * chapters here.
 */
export default class SeriesPage extends Page {
  series: Series | null = null;
  missing = false;
  busy = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    showSeries(routeId(m.route.param('id'))).then(
      ({ data }) => {
        this.series = data;
        app.setTitle(data.title);
        app.history.push('anthology.series', data.title);
        m.redraw();
      },
      () => {
        this.missing = true;
        m.redraw();
      }
    );
  }

  view(): Mithril.Children {
    const series = this.series;

    if (!series) {
      return (
        <PageStructure className="AnthologySeriesPage" loading={!this.missing}>
          {this.missing ? <p className="AnthologyEmpty">{t('series.missing')}</p> : null}
        </PageStructure>
      );
    }

    return (
      <PageStructure className="AnthologySeriesPage" hero={() => this.hero(series)} sidebar={() => this.sidebar(series)}>
        {series.description ? <div className="AnthologyDescription">{series.description}</div> : null}
        <h3 className="AnthologyHeading">{t('series.contents')}</h3>
        {series.chapters.length ? (
          <ol className="AnthologyContents">{series.chapters.map((chapter, i) => this.chapter(series, chapter, i))}</ol>
        ) : (
          <p className="AnthologyEmpty">{series.canEdit ? t('series.empty_owner') : t('series.empty')}</p>
        )}
      </PageStructure>
    );
  }

  hero(series: Series): Mithril.Children {
    return (
      <header className={'Hero AnthologyHero' + (series.coverUrl ? ' AnthologyHero--cover' : '')}>
        {series.coverUrl ? <img className="AnthologyHero-backdrop" src={series.coverUrl} alt="" /> : null}
        <div className="container">
          <div className="AnthologyHero-inner">
            <Cover url={series.coverUrl} title={series.title} className="AnthologyHero-cover" />
            <div className="AnthologyHero-text">
              <Link className="AnthologyHero-crumb" href={app.route('anthology.index')}>
                {t('nav')}
              </Link>
              <h1 className="Hero-title AnthologyHero-title">{series.title}</h1>
              {series.author ? (
                <div className="AnthologyHero-author">
                  {t('card.by', {
                    author: <Link href={app.route('user', { username: series.author.slug })}>{series.author.displayName}</Link>,
                  })}
                </div>
              ) : null}
              <ul className="AnthologyHero-facts">
                <li className={'AnthologyStatus AnthologyStatus--' + series.status}>{t('status.' + series.status)}</li>
                <li>
                  <Icon name="fas fa-list-ol" /> {t('card.chapters', { count: series.chapterCount })}
                </li>
                <li>
                  <Icon name="fas fa-bell" /> {t('card.followers', { count: series.followerCount })}
                </li>
              </ul>
            </div>
          </div>
        </div>
      </header>
    );
  }

  sidebar(series: Series): Mithril.Children {
    const chapters = series.chapters;
    const done = chapters.filter((c) => c.read).length;
    const next = chapters.find((c) => !c.read) || chapters[0];
    const user = app.session.user;

    return (
      <nav className="AnthologySide">
        {chapters.length ? (
          <div className="AnthologySide-card">
            {user ? <ProgressBar done={done} total={chapters.length} /> : null}
            <LinkButton className="Button Button--primary Button--block" icon="fas fa-book-open" href={chapterUrl(next)}>
              {!done ? t('series.start') : done === chapters.length ? t('series.again') : t('series.continue')}
            </LinkButton>
          </div>
        ) : null}

        {series.canFollow || !user ? (
          <div className="AnthologySide-card">
            <Button
              className={'Button Button--block' + (series.following ? ' AnthologyFollow--on' : '')}
              icon={series.following ? 'fas fa-bell' : 'far fa-bell'}
              loading={this.busy}
              aria-pressed={series.following ? 'true' : 'false'}
              onclick={() => this.toggleFollow(series)}
            >
              {series.following ? t('series.following') : t('series.follow')}
            </Button>
            <p className="AnthologySide-note">{t('series.follow_help')}</p>
          </div>
        ) : null}

        {series.canEdit ? (
          <div className="AnthologySide-actions">
            <Button
              className="Button Button--block"
              icon="fas fa-pen"
              onclick={() => app.modal.show(() => import('../modals/SeriesEditModal'), { series, onsave: (card: SeriesCard) => this.saved(card) })}
            >
              {t('series.edit')}
            </Button>
            <Button className="Button Button--block AnthologySide-delete" icon="fas fa-trash" onclick={() => this.remove(series)}>
              {t('series.delete')}
            </Button>
          </div>
        ) : null}

        <Link className="AnthologySide-back" href={app.route('anthology.index')}>
          <Icon name="fas fa-arrow-left" /> {t('series.back')}
        </Link>
      </nav>
    );
  }

  chapter(series: Series, chapter: ChapterItem, i: number): Mithril.Children {
    const last = series.chapters.length - 1;

    return (
      <li key={chapter.id} className={'AnthologyChapter' + (chapter.read ? ' AnthologyChapter--read' : '')}>
        <span className="AnthologyChapter-number" title={chapter.read ? extractText(t('series.read')) : undefined}>
          {chapter.read ? <Icon name="fas fa-check" /> : i + 1}
        </span>
        <Link className="AnthologyChapter-link" href={chapterUrl(chapter)}>
          <span className="AnthologyChapter-title">{chapter.title}</span>
          <span className="AnthologyChapter-meta">
            {t('series.chapter_meta', { date: chapter.createdAt ? humanTime(new Date(chapter.createdAt)) : '', count: chapter.replies })}
          </span>
        </Link>
        {series.canEdit ? (
          <span className="AnthologyChapter-tools">
            <Button
              className="Button Button--icon Button--flat"
              icon="fas fa-arrow-up"
              disabled={i === 0}
              aria-label={extractText(t('series.move_up'))}
              title={extractText(t('series.move_up'))}
              onclick={() => this.move(series, i, -1)}
            />
            <Button
              className="Button Button--icon Button--flat"
              icon="fas fa-arrow-down"
              disabled={i === last}
              aria-label={extractText(t('series.move_down'))}
              title={extractText(t('series.move_down'))}
              onclick={() => this.move(series, i, 1)}
            />
            <Button
              className="Button Button--icon Button--flat"
              icon="fas fa-xmark"
              aria-label={extractText(t('series.remove'))}
              title={extractText(t('series.remove'))}
              onclick={() => this.unlink(series, chapter)}
            />
          </span>
        ) : null}
      </li>
    );
  }

  toggleFollow(series: Series) {
    if (!app.session.user) {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));
      return;
    }
    this.busy = true;
    follow(series.id, !series.following).then(
      ({ data }) => {
        series.following = data.following;
        series.followerCount = data.followerCount;
        this.busy = false;
        m.redraw();
      },
      (e: any) => {
        this.busy = false;
        failed(e);
        m.redraw();
      }
    );
  }

  move(series: Series, i: number, by: number) {
    const chapters = series.chapters;
    const [chapter] = chapters.splice(i, 1);
    chapters.splice(i + by, 0, chapter);
    m.redraw();
    reorder(
      series.id,
      chapters.map((c) => c.id)
    ).catch((e: any) => {
      failed(e);
      this.load();
    });
  }

  unlink(series: Series, chapter: ChapterItem) {
    removeChapter(chapter.id).then(() => {
      series.chapters = series.chapters.filter((c) => c !== chapter);
      series.chapterCount = series.chapters.length;
      // A discussion page already in the store learns it left the series.
      app.store.getById<any>('discussions', String(chapter.id))?.pushAttributes({ anthologySeries: null });
      m.redraw();
    }, failed);
  }

  saved(card: SeriesCard) {
    Object.assign(this.series!, card, { chapterCount: this.series!.chapters.length });
    app.setTitle(card.title);
    // A new title is a new slug: fix the address bar without reloading the page.
    window.history.replaceState(window.history.state, '', seriesUrl(card));
    m.redraw();
  }

  remove(series: Series) {
    if (!confirm(extractText(t('series.delete_confirm', { title: series.title })))) return;
    deleteSeries(series.id).then(() => m.route.set(app.route('anthology.index')), failed);
  }
}
