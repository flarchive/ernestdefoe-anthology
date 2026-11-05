import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexPage from 'flarum/forum/components/IndexPage';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Select from 'flarum/common/components/Select';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { listSeries } from '../../common/api';
import { seriesUrl } from '../../common/urls';
import type { SeriesCard as Card, TagOption } from '../../common/api';
import SeriesCard from '../components/SeriesCard';
import { failed, t } from '../util';

const STATUSES = ['', 'ongoing', 'complete'];
const SORTS = ['latest', 'new', 'popular', 'title'];

/**
 * /series — every series with a chapter the reader can open: status tabs,
 * tag, sort and title search. It can be the forum's home page (Basics), and
 * then it shows the home page's banner (core's welcome banner, or whatever an
 * extension puts there) and links to the discussion list.
 */
export default class DirectoryPage extends Page {
  series: Card[] = [];
  loading = true;
  next: number | null = null;
  status = '';
  tag = '';
  tags: TagOption[] = [];
  sort = 'latest';
  query = '';
  timer = 0;
  asked = 0;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    const title = extractText(t('nav'));
    app.setTitle(title);
    app.history.push('anthology', title);
    this.load();
  }

  load(more = false) {
    this.loading = true;
    const filter: Record<string, string> = {};
    if (this.status) filter.status = this.status;
    if (this.tag) filter.tag = this.tag;
    if (this.query.trim()) filter.q = this.query.trim();
    const params: Record<string, unknown> = { filter, sort: this.sort };
    if (more && this.next) params.page = { offset: this.next };
    // The tag menu comes with the first page.
    const first = !more && !this.asked;
    if (first) params.tags = 1;

    const ask = ++this.asked;
    listSeries(params).then(
      ({ data, meta }) => {
        // An older search answering late doesn't overwrite a newer one.
        if (ask !== this.asked) return;
        this.series = more ? [...this.series, ...data] : data;
        this.next = meta.next;
        if (first) this.tags = meta.tags || [];
        this.loading = false;
        m.redraw();
      },
      (e: any) => {
        this.loading = false;
        failed(e);
        m.redraw();
      }
    );
  }

  view(): Mithril.Children {
    const sorts: Record<string, string> = {};
    SORTS.forEach((s) => (sorts[s] = extractText(t('directory.sort.' + s))));
    const tags: Record<string, string> = { '': extractText(t('directory.all_tags')) };
    this.tags.forEach((tag) => (tags[tag.slug] = tag.name));
    const home = app.forum.attribute('defaultRoute') === '/series';

    return (
      <PageStructure
        className="AnthologyDirectoryPage"
        hero={home ? () => IndexPage.prototype.hero.call(this as any) : undefined}
        sidebar={() => <IndexSidebar />}
      >
        <div className="AnthologyHead">
          <h2 className="AnthologyHead-title">{t('nav')}</h2>
          <div className="AnthologyHead-actions">
            {home ? (
              <LinkButton className="Button" icon="fas fa-comments" href={app.route('index')}>
                {t('directory.forum')}
              </LinkButton>
            ) : null}
            {app.forum.attribute('canCreateSeries') ? (
              <Button
                className="Button Button--primary"
                icon="fas fa-plus"
                onclick={() => app.modal.show(() => import('../modals/SeriesEditModal'), { onsave: (card: Card) => m.route.set(seriesUrl(card)) })}
              >
                {t('directory.new')}
              </Button>
            ) : null}
          </div>
        </div>

        <div className="AnthologyToolbar">
          <div className="AnthologyTabs" role="tablist">
            {STATUSES.map((status) => (
              <Button
                className={'Button Button--flat' + (this.status === status ? ' active' : '')}
                role="tab"
                aria-selected={this.status === status ? 'true' : 'false'}
                onclick={() => {
                  this.status = status;
                  this.load();
                }}
              >
                {status ? t('status.' + status) : t('directory.all')}
              </Button>
            ))}
          </div>
          {this.tags.length ? (
            <Select
              className="AnthologyToolbar-tag"
              options={tags}
              value={this.tag}
              aria-label={extractText(t('directory.tag_label'))}
              onchange={(value: string) => {
                this.tag = value;
                this.load();
              }}
            />
          ) : null}
          <Select
            options={sorts}
            value={this.sort}
            aria-label={extractText(t('directory.sort_label'))}
            onchange={(value: string) => {
              this.sort = value;
              this.load();
            }}
          />
          <input
            className="FormControl AnthologyToolbar-search"
            type="search"
            placeholder={extractText(t('directory.search'))}
            aria-label={extractText(t('directory.search'))}
            value={this.query}
            oninput={(e: InputEvent) => {
              this.query = (e.target as HTMLInputElement).value;
              window.clearTimeout(this.timer);
              this.timer = window.setTimeout(() => this.load(), 300);
            }}
          />
        </div>

        <div className="AnthologyGrid">
          {this.series.map((series) => (
            <SeriesCard key={series.id} series={series} />
          ))}
        </div>
        {this.loading ? <LoadingIndicator display="block" /> : null}
        {!this.loading && !this.series.length ? (
          <p className="AnthologyEmpty">
            {this.query || this.status || this.tag
              ? t('directory.no_results')
              : t(app.forum.attribute('canCreateSeries') ? 'directory.empty' : 'directory.empty_guest')}
          </p>
        ) : null}
        {!this.loading && this.next ? (
          <Button className="Button AnthologyMore" onclick={() => this.load(true)}>
            {t('directory.more')}
          </Button>
        ) : null}
      </PageStructure>
    );
  }
}
