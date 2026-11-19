import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import { listSeries } from '../../common/api';
import { seriesUrl } from '../../common/urls';
import type { SeriesCard as Card } from '../../common/api';
import SeriesCard from '../components/SeriesCard';
import { failed, t } from '../util';

/** /u/:username/series — a member's series. Their own empty ones show to them only. */
export default class UserSeriesPage extends UserPage {
  series: Card[] | null = null;
  shownFor: string | null = null;
  next: number | null = null;
  loading = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: User): void {
    super.show(user);
    // UserPage shows the user again whenever it refreshes them.
    if (this.shownFor === user.id()) return;
    this.shownFor = user.id()!;
    this.series = null;
    app.setTitle(extractText(t('profile.nav')) + ' – ' + user.displayName());
    this.load(user);
  }

  /** A page of 24; Load more for the next. */
  load(user: User, offset = 0) {
    this.loading = true;
    listSeries({ filter: { user: user.id() }, sort: 'latest', page: { offset } }).then(
      ({ data, meta }) => {
        this.series = offset ? [...(this.series || []), ...data] : data;
        this.next = meta.next;
        this.loading = false;
        m.redraw();
      },
      (e: any) => {
        this.series = this.series || [];
        this.loading = false;
        failed(e);
        m.redraw();
      }
    );
  }

  content(): Mithril.Children {
    const own = this.user === app.session.user && app.forum.attribute('canCreateSeries');

    return (
      <div className="AnthologyUserPage">
        {own ? (
          <div className="AnthologyHead">
            <span />
            <Button
              className="Button Button--primary"
              icon="fas fa-plus"
              onclick={() => app.modal.show(() => import('../modals/SeriesEditModal'), { onsave: (card: Card) => m.route.set(seriesUrl(card)) })}
            >
              {t('directory.new')}
            </Button>
          </div>
        ) : null}
        {this.series === null ? (
          <LoadingIndicator display="block" />
        ) : this.series.length ? (
          <div className="AnthologyGrid">
            {this.series.map((series) => (
              <SeriesCard key={series.id} series={series} />
            ))}
          </div>
        ) : (
          <p className="AnthologyEmpty">{t('profile.empty')}</p>
        )}
        {this.series && this.next ? (
          <Button className="Button AnthologyMore" loading={this.loading} onclick={() => this.load(this.user!, this.next!)}>
            {t('directory.more')}
          </Button>
        ) : null}
      </div>
    );
  }
}
