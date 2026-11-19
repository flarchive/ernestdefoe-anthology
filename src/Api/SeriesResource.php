<?php

namespace Ernestdefoe\Anthology\Api;

use Carbon\Carbon;
use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Chapter;
use Ernestdefoe\Anthology\Covers;
use Ernestdefoe\Anthology\Follow;
use Ernestdefoe\Anthology\Reading;
use Ernestdefoe\Anthology\Series;
use Ernestdefoe\Anthology\SeriesWord;
use Ernestdefoe\Anthology\SeriesWords;
use Ernestdefoe\Anthology\Tags\SeriesTags;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Locale\TranslatorInterface;
use Flarum\Post\Exception\FloodingException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * Series (type `anthology-series`): the directory (filters in Search\), a member's series, the
 * series page, and creating, editing, following, reordering and deleting.
 *
 * What a reader is shown goes through discussion visibility: a series appears
 * once it has a chapter they can open, and every count is of those chapters.
 * Its owner (and moderators) also see it while it is empty, so they can file
 * discussions under it. The cover upload, being multipart, is CoverController.
 *
 * @extends AbstractDatabaseResource<Series>
 */
class SeriesResource extends AbstractDatabaseResource
{
    public const PER_PAGE = 24;
    public const PER_HOUR = 10;
    public const MAX_DESCRIPTION = 5000;

    public function __construct(
        private readonly Covers $covers,
        private readonly Reading $reading,
        private readonly SeriesTags $tags,
        private readonly SeriesWords $words,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function type(): string
    {
        return 'anthology-series';
    }

    public function model(): string
    {
        return Series::class;
    }

    /** Show, update and delete: the same series and chapter counts as the directory (SeriesSearcher). */
    public function scope(Builder $query, OriginalContext $context): void
    {
        $actor = $context->getActor();
        $query->whereVisibleTo($actor)->withCount(['discussions as chapter_count' => fn ($d) => $d->whereVisibleTo($actor)]);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->can('anthology.view')
                ->paginate(self::PER_PAGE, self::PER_PAGE)
                ->defaultInclude(['user'])
                ->defaultSort('-lastChapterAt')
                // The tag menu (Tags on) with the directory's first page: one query, only when asked.
                ->meta([
                    Schema\Arr::make('tags')
                        ->visible(fn (Context $context) => (bool) $context->queryParam('withTags'))
                        ->get(fn (Context $context) => $this->tags->menu($context->getActor())),
                ]),
            Endpoint\Show::make()
                ->can('anthology.view')
                ->defaultInclude(['user']),
            Endpoint\Create::make()
                ->authenticated()
                ->can('anthology.create')
                ->defaultInclude(['user']),
            // No endpoint-wide ability: a reader may write `following` only, the owner and moderators the rest
            // (SeriesPolicy's rule, Access::canEdit). Each field's writable() decides, so anything else is refused.
            Endpoint\Update::make()
                ->authenticated()
                ->defaultInclude(['user']),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('lastChapterAt'),
            SortColumn::make('createdAt'),
            SortColumn::make('followerCount'),
            SortColumn::make('title'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var Series $model */
        $actor = $context->getActor();

        // One indexed count, on creation only: enough series for any writer, not a flood.
        if (! Access::canModerate($actor)
            && Series::query()->where('user_id', $actor->id)->where('created_at', '>', Carbon::now()->subHour())->count() >= self::PER_HOUR) {
            throw new FloodingException();
        }

        $model->user_id ??= (int) $actor->id;
        $model->status ??= 'ongoing';
        $model->follower_count = 0;
        $model->last_chapter_at = Carbon::now();

        return $model;
    }

    public function created(object $model, OriginalContext $context): ?object
    {
        /** @var Series $model */
        $this->words->sync($model);

        return $model;
    }

    public function updated(object $model, OriginalContext $context): ?object
    {
        /** @var Series $model */
        if ($model->wasChanged('title')) {
            $this->words->sync($model);
        }

        return $model;
    }

    /** Its chapters (the discussions stay), follows and search words go with it, without relying on the database's foreign keys being enforced. */
    public function deleting(object $model, OriginalContext $context): void
    {
        /** @var Series $model */
        Chapter::query()->where('series_id', $model->id)->delete();
        Follow::query()->where('series_id', $model->id)->delete();
        SeriesWord::query()->where('series_id', $model->id)->delete();
    }

    /** The rows go together or not at all; the cover file only once they are gone. */
    public function deleteAction(object $model, OriginalContext $context): void
    {
        /** @var Series $model */
        $cover = $model->cover_path;
        $model->getConnection()->transaction(fn () => parent::deleteAction($model, $context));
        $this->covers->delete($cover);
    }

    public function fields(): array
    {
        return [...$this->content(), ...$this->reader(), ...$this->abilities(), ...$this->relationships()];
    }

    /** @return list<Schema\Attribute> */
    private function content(): array
    {
        $editor = fn (Series $s, Context $context) => ! $s->exists || Access::canEdit($context->getActor(), $s);

        return [
            Schema\Str::make('title')
                ->writable($editor)
                ->requiredOnCreate()
                ->rule(fn () => fn ($attribute, $value, $fail) => $this->validTitle((string) $value, $fail))
                ->set(function (Series $s, $value) {
                    $s->title = self::squash((string) $value);
                    $s->slug = Str::limit(Str::slug($s->title), 190, '');
                }),
            Schema\Str::make('slug'),
            Schema\Str::make('description')
                ->nullable()
                ->writable($editor)
                ->rule(fn () => fn ($attribute, $value, $fail) => mb_strlen(trim((string) $value)) > self::MAX_DESCRIPTION
                    ? $fail($this->message('description_too_long', ['{max}' => (string) self::MAX_DESCRIPTION])) : null)
                ->set(fn (Series $s, $value) => $s->description = trim(str_replace("\r\n", "\n", (string) $value)) ?: null),
            Schema\Str::make('excerpt')
                ->nullable()
                ->get(fn (Series $s) => $s->description ? Str::limit(self::squash($s->description), 160) : null),
            Schema\Str::make('status')
                ->writable(fn (Series $s, Context $context) => $s->exists && Access::canEdit($context->getActor(), $s))
                ->rule(fn () => fn ($attribute, $value, $fail) => in_array($value, Series::STATUSES, true) ? null : $fail($this->message('bad_status'))),
            Schema\Str::make('coverUrl')->nullable()->get(fn (Series $s) => $this->covers->url($s)),
            Schema\Integer::make('chapterCount')->get(fn (Series $s) => (int) ($s->getAttribute('chapter_count') ?? 0)),
            Schema\Integer::make('followerCount')->property('follower_count'),
            Schema\DateTime::make('lastChapterAt')->property('last_chapter_at'),
            Schema\DateTime::make('createdAt')->property('created_at'),
        ];
    }

    /**
     * The series page's table of contents with the reader's progress, whether they follow it, and the owner's new
     * reading order. On the series page only (Reading): the visible chapters, plus for a signed-in reader which they
     * have read and whether they follow it.
     *
     * @return list<Schema\Attribute>
     */
    private function reader(): array
    {
        $follower = fn (Series $s, Context $context) => $s->exists && ! $context->getActor()->isGuest() && $s->user_id !== $context->getActor()->id;

        return [
            Schema\Arr::make('chapters')
                ->visible(fn (Series $s, Context $context) => $context->showing(self::class))
                ->get(fn (Series $s, Context $context) => $this->reading->chapters($s, $context->getActor())),
            Schema\Boolean::make('following')
                ->visible(fn (Series $s, Context $context) => $context->showing(self::class) || $context->updating(self::class))
                ->get(fn (Series $s, Context $context) => $this->reading->follows($s, $context->getActor()))
                ->writable($follower)
                ->save(fn (Series $s, $value, Context $context) => $this->reading->follow($s, $context->getActor(), (bool) $value)),
            // Write-only: discussion ids in the new reading order.
            Schema\Arr::make('chapterOrder')
                ->visible(false)
                ->writable(fn (Series $s, Context $context) => $s->exists && Access::canEdit($context->getActor(), $s))
                ->save(fn (Series $s, $value) => $this->reorder($s, (array) $value)),
        ];
    }

    /** @return list<Schema\Attribute> */
    private function abilities(): array
    {
        return [
            Schema\Boolean::make('canEdit')->get(fn (Series $s, Context $context) => Access::canEdit($context->getActor(), $s)),
            Schema\Boolean::make('canDelete')->get(fn (Series $s, Context $context) => Access::canEdit($context->getActor(), $s)),
            Schema\Boolean::make('canFollow')
                ->get(fn (Series $s, Context $context) => ! $context->getActor()->isGuest() && $s->user_id !== $context->getActor()->id),
        ];
    }

    /** @return list<Schema\Relationship\ToOne> */
    private function relationships(): array
    {
        return [
            // A moderator may start one for another member, to file that member's discussions under it.
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable()
                ->writable(fn (Series $s, Context $context) => ! $s->exists && Access::canModerate($context->getActor()))
                ->set(fn (Series $s, ?User $user) => $s->user_id = $user?->id),
        ];
    }

    /**
     * The owner's new reading order. Chapters the list leaves out (ones the owner can't see, say) keep their order
     * after the ones it names. Only moved rows are written, in one query.
     *
     * @param array<mixed> $ids
     */
    private function reorder(Series $series, array $ids): void
    {
        $wanted = array_values(array_unique(array_filter(array_map(fn ($id) => is_numeric($id) ? (int) $id : 0, $ids))));
        $positions = Chapter::query()->where('series_id', $series->id)->orderBy('position')->orderBy('discussion_id')->pluck('position', 'discussion_id');
        $order = array_merge(array_values(array_filter($wanted, fn ($id) => $positions->has($id))), $positions->keys()->diff($wanted)->values()->all());

        // discussion id => new position, for the rows that move.
        $moved = [];
        foreach ($order as $i => $id) {
            if ((int) $positions[$id] !== $i + 1) {
                $moved[(int) $id] = $i + 1;
            }
        }
        if (! $moved) {
            return;
        }

        // One UPDATE … SET position = CASE discussion_id WHEN … THEN … END, scoped to this series' rows, so a chapter
        // taken out meanwhile is simply not there to move. Only integers go into the CASE, so it is safe to inline.
        $column = Chapter::query()->getQuery()->getGrammar()->wrap('discussion_id');
        $case = 'CASE '.$column;
        foreach ($moved as $id => $position) {
            $case .= ' WHEN '.$id.' THEN '.$position;
        }
        Chapter::query()->where('series_id', $series->id)->whereIn('discussion_id', array_keys($moved))
            ->update(['position' => new Expression($case.' END')]);
    }

    private function validTitle(string $value, callable $fail): void
    {
        $title = self::squash($value);
        if ($title === '') {
            $fail($this->message('title_required'));
        } elseif (mb_strlen($title) > 200) {
            $fail($this->message('title_too_long'));
        }
    }

    /** @param array<string, string> $params */
    private function message(string $key, array $params = []): string
    {
        return $this->translator->trans('ernestdefoe-anthology.api.'.$key, $params);
    }

    private static function squash(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
