import app from 'flarum/common/app';
import type Discussion from 'flarum/common/models/Discussion';
import type User from 'flarum/common/models/User';
import type SeriesModel from './models/SeriesModel';

export type Status = 'ongoing' | 'complete';

export interface Person {
  id: number;
  username: string;
  displayName: string;
  avatarUrl: string | null;
  slug: string;
}

export interface SeriesCard {
  id: number;
  slug: string;
  title: string;
  excerpt: string | null;
  coverUrl: string | null;
  status: Status;
  chapterCount: number;
  followerCount: number;
  lastChapterAt: string;
  author: Person | null;
  canEdit: boolean;
  description?: string | null;
}

export interface ChapterItem {
  id: number;
  slug: string;
  title: string;
  createdAt: string | null;
  replies: number;
  read: boolean;
}

export interface Series extends SeriesCard {
  description: string | null;
  createdAt: string;
  chapters: ChapterItem[];
  following: boolean;
  canFollow: boolean;
  canDelete: boolean;
}

export interface Ref {
  id: number;
  slug: string;
  title: string;
}

/** What a chapter's discussion page carries (the `anthologySeries` attribute). */
export interface DiscussionSeries extends Ref {
  coverUrl: string | null;
  number: number | null;
  count: number;
  prev: Ref | null;
  next: Ref | null;
  canEdit: boolean;
}

/** A tag in the directory's tag menu (Tags on). */
export interface TagOption {
  id: number;
  slug: string;
  name: string;
  color: string | null;
  icon: string | null;
}

const url = (path: string) => app.forum.attribute('apiUrl') + path;

const person = (user: User | null | false): Person | null =>
  user
    ? { id: Number(user.id()), username: user.username(), displayName: user.displayName(), avatarUrl: user.avatarUrl() || null, slug: user.slug() }
    : null;

const card = (s: SeriesModel): SeriesCard => ({
  id: Number(s.id()),
  slug: s.slug(),
  title: s.title(),
  excerpt: s.excerpt(),
  coverUrl: s.coverUrl(),
  status: s.status(),
  chapterCount: s.chapterCount(),
  followerCount: s.followerCount(),
  lastChapterAt: s.lastChapterAt(),
  author: person(s.user()),
  canEdit: s.canEdit(),
  description: s.description(),
});

const full = (s: SeriesModel): Series => ({
  ...card(s),
  description: s.description(),
  createdAt: s.createdAt(),
  chapters: s.chapters() || [],
  following: !!s.following(),
  canFollow: s.canFollow(),
  canDelete: s.canDelete(),
});

// The directory's sort names and filters, as the series resource knows them.
const SORTS: Record<string, string> = { new: '-createdAt', popular: '-followerCount', title: 'title' };

/** A series from the store, loaded first if this page hasn't seen it. */
const stored = (id: number): Promise<SeriesModel> => {
  const model = app.store.getById<SeriesModel>('anthology-series', String(id));
  return model ? Promise.resolve(model) : app.store.find<SeriesModel>('anthology-series', String(id));
};

/**
 * `filter`: user, mine, tag, status, q (the start of a title); `published` keeps series with a chapter to open
 * (the directory). `sort`: latest (default), new, popular, title. `tags`: the tag menu comes too.
 */
export const listSeries = (params: {
  filter?: Record<string, unknown>;
  sort?: string;
  page?: { offset?: number };
  tags?: number;
  published?: boolean;
}) => {
  const { q, ...filter } = (params.filter || {}) as Record<string, unknown>;
  const offset = params.page?.offset || 0;
  const query: Record<string, unknown> = {
    filter: { ...filter, ...(q ? { title: q } : {}), ...(params.published ? { published: '1' } : {}) },
    sort: SORTS[params.sort || ''] || '-lastChapterAt',
    page: { offset },
  };
  if (params.tags) query.withTags = 1;

  return app.store.find<SeriesModel[]>('anthology-series', query).then((list) => ({
    data: list.map(card),
    meta: { next: list.payload.links?.next ? offset + list.length : null, tags: (list.payload.meta?.tags as TagOption[] | null) || undefined },
  }));
};

export const showSeries = (id: number) => app.store.find<SeriesModel>('anthology-series', String(id)).then((s) => ({ data: full(s) }));

/** `userId` (moderators): start it for that member, who is in the store with their discussion. */
export const saveSeries = (id: number | null, { userId, ...attributes }: Record<string, unknown>) => {
  const owner = !id && userId ? app.store.getById<User>('users', String(userId)) : undefined;
  const model = id ? stored(id) : Promise.resolve(app.store.createRecord<SeriesModel>('anthology-series'));
  return model.then((s) => s.save({ ...attributes, ...(owner ? { relationships: { user: owner } } : {}) })).then((s) => ({ data: card(s) }));
};

export const deleteSeries = (id: number) => stored(id).then((s) => s.delete());

export const uploadCover = (id: number, file: File) => {
  const body = new FormData();
  body.append('cover', file);
  return app.request<{ data: { coverUrl: string | null } }>({
    method: 'POST',
    url: url('/anthology-series/' + id + '/cover'),
    serialize: (raw: any) => raw,
    body,
  });
};

export const removeCover = (id: number) =>
  app.request<{ data: { coverUrl: null } }>({ method: 'DELETE', url: url('/anthology-series/' + id + '/cover') });

export const follow = (id: number, on: boolean) =>
  stored(id)
    .then((s) => s.save({ following: on }))
    .then((s) => ({ data: { following: !!s.following(), followerCount: s.followerCount() } }));

export const reorder = (id: number, ids: number[]) => stored(id).then((s) => s.save({ chapterOrder: ids }));

/** Files a discussion under a series (null takes it out); the answer is what its page shows. */
const fileChapter = (discussionId: number, seriesId: number | null) => {
  const known = app.store.getById<Discussion>('discussions', String(discussionId));
  return (known ? Promise.resolve(known) : app.store.find<Discussion>('discussions', String(discussionId)))
    .then((d) => d.save({ anthologySeriesId: seriesId }))
    .then((d) => ({ data: d.attribute<DiscussionSeries | null>('anthologySeries') }));
};

export const addChapter = (discussionId: number, seriesId: number) => fileChapter(discussionId, seriesId);

export const removeChapter = (discussionId: number) => fileChapter(discussionId, null);
