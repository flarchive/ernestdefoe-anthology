import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { t } from '../util';

/** "{user} added a new chapter to {series}", opening the chapter. */
export default class NewChapterNotification extends Notification {
  icon() {
    return 'fas fa-book-bookmark';
  }

  href() {
    const discussion = this.attrs.notification.subject() as any;
    return discussion ? app.route.discussion(discussion) : app.route('anthology.index');
  }

  content() {
    const n = this.attrs.notification;
    return t('notifications.new_chapter', { user: n.fromUser(), series: (n.content() as any)?.seriesTitle || '' });
  }

  excerpt() {
    return (this.attrs.notification.subject() as any)?.title?.() || null;
  }
}
