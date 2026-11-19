import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';
import { addChapter, listSeries, removeChapter, saveSeries } from '../../common/api';
import type { DiscussionSeries, SeriesCard } from '../../common/api';
import Cover from '../components/Cover';
import { failed, t } from '../util';

interface Attrs extends IFormModalAttrs {
  discussion: Discussion;
}

/**
 * "Add to a series" from a discussion's menu: pick one of the author's
 * series, or start a new one, and the discussion becomes its newest chapter.
 * Also where a chapter is moved or taken out again.
 */
export default class AddToSeriesModal extends FormModal<Attrs> {
  options: SeriesCard[] | null = null;
  next: number | null = null;
  more = false;
  chosen: number | null = null;
  newTitle = Stream('');

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    this.load();
  }

  /** The author's series, 24 at a time: Load more for the rest. A moderator files a discussion under its author's series, not their own. */
  load(offset = 0) {
    if (this.more) return;
    this.more = true;
    const author = this.attrs.discussion.user();
    const filter = author && author !== app.session.user ? { user: author.id() } : { mine: '1' };

    listSeries({ filter, sort: 'title', page: { offset } }).then(
      ({ data, meta }) => {
        this.options = offset ? [...(this.options || []), ...data] : data;
        this.next = meta.next;
        this.more = false;
        if (!offset) {
          const current = this.current();
          this.chosen = current ? current.id : data[0]?.id || null;
        }
        m.redraw();
      },
      (e: any) => {
        this.options = this.options || [];
        this.more = false;
        failed(e);
        m.redraw();
      }
    );
  }

  current(): DiscussionSeries | null {
    return this.attrs.discussion.attribute<DiscussionSeries | null>('anthologySeries') || null;
  }

  /** Someone else's discussion: a moderator files it under that author's series. */
  forOther(): boolean {
    const author = this.attrs.discussion.user();
    return !!author && author !== app.session.user;
  }

  /** A new series always belongs to the discussion's author; a moderator may start one for them. */
  canStart(): boolean {
    return this.forOther() ? !!app.forum.attribute('canModerateSeries') : !!app.forum.attribute('canCreateSeries');
  }

  className() {
    return 'AnthologyAddModal Modal--small';
  }

  title() {
    return this.current() ? t('add_modal.change_title') : t('add_modal.title');
  }

  content(): Mithril.Children {
    const current = this.current();

    return (
      <div className="Modal-body">
        <p className="AnthologyAddModal-intro">{t('add_modal.intro')}</p>
        {current ? (
          <div className="AnthologyAddModal-current">
            <span>
              {t('add_modal.current')} <strong>{current.title}</strong>
            </span>
            <Button className="Button Button--link" icon="fas fa-xmark" loading={this.loading} onclick={() => this.remove(current)}>
              {t('add_modal.remove', { title: current.title })}
            </Button>
          </div>
        ) : null}
        {this.choices(current)}
        {this.canStart() ? this.startNew() : null}
      </div>
    );
  }

  /** The series to file it under, and the button that does. */
  choices(current: DiscussionSeries | null): Mithril.Children {
    if (this.options === null) return <LoadingIndicator display="block" />;
    if (!this.options.length) {
      return (
        <p className="AnthologyEmpty">{this.forOther() ? t('add_modal.none_other', { user: this.attrs.discussion.user() }) : t('add_modal.none')}</p>
      );
    }

    return [
      <div className="AnthologyAddModal-list" role="radiogroup">
        {this.options.map((series) => (
          <label className={'AnthologyAddModal-option' + (this.chosen === series.id ? ' active' : '')}>
            <input type="radio" name="series" checked={this.chosen === series.id} onchange={() => (this.chosen = series.id)} />
            <Cover url={series.coverUrl} title={series.title} className="AnthologyAddModal-cover" />
            <span className="AnthologyAddModal-name">
              <span className="AnthologyAddModal-title">{series.title}</span>
              <span className="AnthologyAddModal-meta">{t('card.chapters', { count: series.chapterCount })}</span>
            </span>
          </label>
        ))}
        {this.next ? (
          <Button className="Button Button--link AnthologyAddModal-more" loading={this.more} onclick={() => this.load(this.next!)}>
            {t('directory.more')}
          </Button>
        ) : null}
      </div>,
      <div className="Form-group">
        <Button
          className="Button Button--primary Button--block"
          type="submit"
          loading={this.loading}
          disabled={!this.chosen || this.chosen === current?.id}
        >
          {t('add_modal.add')}
        </Button>
      </div>,
    ];
  }

  /** Start a new series (for the discussion's author, when a moderator does it) and file it there. */
  startNew(): Mithril.Children {
    return (
      <div className="AnthologyAddModal-new">
        <label for="anthology-new-title">{t('add_modal.new')}</label>
        {this.forOther() ? <p className="helpText">{t('add_modal.new_for', { user: this.attrs.discussion.user() })}</p> : null}
        <div className="AnthologyAddModal-newRow">
          <input
            id="anthology-new-title"
            className="FormControl"
            maxlength={200}
            placeholder={extractText(t('add_modal.new_title'))}
            bidi={this.newTitle}
          />
          <Button className="Button" loading={this.loading} disabled={!this.newTitle().trim()} onclick={() => this.createAndAdd()}>
            {t('add_modal.create_and_add')}
          </Button>
        </div>
      </div>
    );
  }

  onready() {
    // Focus nothing: the first field is a radio, and the keyboard would jump on a phone.
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();
    if (this.chosen) this.add(this.chosen);
  }

  add(seriesId: number) {
    this.loading = true;
    addChapter(Number(this.attrs.discussion.id()), seriesId).then(({ data }) => this.done(data, 'add_modal.added'), this.fail.bind(this));
  }

  createAndAdd() {
    const title = this.newTitle().trim();
    if (!title) return;
    this.loading = true;
    const author = this.attrs.discussion.user();
    const data: Record<string, unknown> = { title };
    if (author && this.forOther()) data.userId = Number(author.id());
    saveSeries(null, data).then(({ data }) => this.add(data.id), this.fail.bind(this));
  }

  remove(current: DiscussionSeries) {
    this.loading = true;
    removeChapter(Number(this.attrs.discussion.id())).then(() => {
      this.attrs.discussion.pushAttributes({ anthologySeries: null });
      app.alerts.show({ type: 'success' }, t('add_modal.removed', { title: current.title }));
      this.hide();
      m.redraw();
    }, this.fail.bind(this));
  }

  done(data: DiscussionSeries | null, message: string) {
    this.attrs.discussion.pushAttributes({ anthologySeries: data });
    if (data) app.alerts.show({ type: 'success' }, t(message, { title: data.title }));
    this.hide();
    m.redraw();
  }

  fail(error: any) {
    this.loading = false;
    this.onerror(error);
  }
}
