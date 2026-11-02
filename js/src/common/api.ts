import app from 'flarum/common/app';

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

const api = (path: string) => app.forum.attribute('apiUrl') + '/anthology' + path;

export const listSeries = (params: Record<string, unknown>) =>
  app.request<{ data: SeriesCard[]; meta: { next: number | null } }>({ method: 'GET', url: api('/series'), params });

export const showSeries = (id: number) => app.request<{ data: Series }>({ method: 'GET', url: api('/series/' + id) });

export const saveSeries = (id: number | null, data: Record<string, unknown>) =>
  app.request<{ data: SeriesCard }>({ method: id ? 'PATCH' : 'POST', url: api('/series' + (id ? '/' + id : '')), body: { data } });

export const deleteSeries = (id: number) => app.request({ method: 'DELETE', url: api('/series/' + id) });

export const uploadCover = (id: number, file: File) => {
  const body = new FormData();
  body.append('cover', file);
  return app.request<{ data: { coverUrl: string | null } }>({
    method: 'POST',
    url: api('/series/' + id + '/cover'),
    serialize: (raw: any) => raw,
    body,
  });
};

export const removeCover = (id: number) => app.request<{ data: { coverUrl: null } }>({ method: 'DELETE', url: api('/series/' + id + '/cover') });

export const follow = (id: number, on: boolean) =>
  app.request<{ data: { following: boolean; followerCount: number } }>({ method: on ? 'POST' : 'DELETE', url: api('/series/' + id + '/follow') });

export const reorder = (id: number, ids: number[]) => app.request({ method: 'PATCH', url: api('/series/' + id + '/order'), body: { data: { ids } } });

export const addChapter = (discussionId: number, seriesId: number) =>
  app.request<{ data: DiscussionSeries }>({ method: 'POST', url: api('/chapters'), body: { data: { discussionId, seriesId } } });

export const removeChapter = (discussionId: number) => app.request<{ data: null }>({ method: 'DELETE', url: api('/chapters/' + discussionId) });
