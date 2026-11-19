<?php

namespace Ernestdefoe\Anthology;

use Illuminate\Database\Eloquent\Builder;

/**
 * The search index of series titles (table anthology_series_words): each
 * title's words, lowercased. A search matches titles that have a word starting
 * with each word typed, so it never needs a leading wildcard.
 */
class SeriesWords
{
    public const MAX_WORDS = 40;
    public const MAX_TYPED = 6;

    /** @return list<string> */
    public static function split(string $text, int $limit = self::MAX_WORDS): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_map(fn ($w) => mb_substr($w, 0, 100), $words))), 0, $limit);
    }

    /** Replaces a series' words after its title is saved: one delete and one insert. */
    public function sync(Series $series): void
    {
        SeriesWord::query()->where('series_id', $series->id)->delete();
        SeriesWord::query()->insert(array_map(fn ($word) => ['series_id' => $series->id, 'word' => $word], self::split($series->title)));
    }

    /** @param Builder<Series> $query */
    public function filter(Builder $query, string $typed): void
    {
        foreach (self::split(mb_substr($typed, 0, 100), self::MAX_TYPED) as $word) {
            $query->whereIn('anthology_series.id', SeriesWord::query()->select('series_id')->where('word', 'like', addcslashes($word, '\\%_').'%'));
        }
    }
}
