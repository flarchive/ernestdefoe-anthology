import app from 'flarum/common/app';
import type { Ref } from './api';

/** "/series/12-the-long-road", or "/series/12" for a title with no Latin letters. */
export const seriesUrl = (series: { id: number; slug?: string | null }) =>
  app.route('anthology.series', { id: series.id + (series.slug ? '-' + series.slug : '') });

export const chapterUrl = (chapter: Ref) => app.route('discussion', { id: chapter.id + (chapter.slug ? '-' + chapter.slug : '') });

/** The id at the front of an "id-slug" route parameter. */
export const routeId = (param: string) => parseInt(String(param || ''), 10) || 0;
