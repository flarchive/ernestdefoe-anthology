import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type { ChapterItem } from '../api';

/** A series (`anthology-series`), as the store keeps it. api.ts turns it into the plain objects the pages use. */
export default class SeriesModel extends Model {
  slug = Model.attribute<string>('slug');
  title = Model.attribute<string>('title');
  description = Model.attribute<string | null>('description');
  excerpt = Model.attribute<string | null>('excerpt');
  coverUrl = Model.attribute<string | null>('coverUrl');
  status = Model.attribute<'ongoing' | 'complete'>('status');
  chapterCount = Model.attribute<number>('chapterCount');
  followerCount = Model.attribute<number>('followerCount');
  lastChapterAt = Model.attribute<string>('lastChapterAt');
  createdAt = Model.attribute<string>('createdAt');
  canEdit = Model.attribute<boolean>('canEdit');
  canDelete = Model.attribute<boolean>('canDelete');
  canFollow = Model.attribute<boolean>('canFollow');
  following = Model.attribute<boolean | undefined>('following');
  chapters = Model.attribute<ChapterItem[] | undefined>('chapters');
  user = Model.hasOne<User | null>('user');
}
