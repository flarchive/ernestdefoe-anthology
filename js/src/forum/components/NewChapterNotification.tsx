import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Discussion from 'flarum/common/models/Discussion';
import { t } from '../util';

/** What the notification stores (NewChapterBlueprint::getData). */
interface NewChapterData {
  seriesId: number;
  seriesTitle: string;
}

/** "{user} added a new chapter to {series}", opening the chapter. */
export default class NewChapterNotification extends Notification {
  icon() {
    return 'fas fa-book-bookmark';
  }

  /** The subject is the chapter's discussion. */
  chapter(): Discussion | null {
    return (this.attrs.notification.subject() as Discussion | false | undefined) || null;
  }

  href() {
    const discussion = this.chapter();
    return discussion ? app.route.discussion(discussion) : app.route('anthology.index');
  }

  content() {
    const n = this.attrs.notification;
    return t('notifications.new_chapter', { user: n.fromUser(), series: n.content<NewChapterData>()?.seriesTitle || '' });
  }

  excerpt() {
    return this.chapter()?.title() || null;
  }
}
