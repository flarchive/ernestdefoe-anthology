<?php

namespace Ernestdefoe\Anthology\Api;

use Carbon\Carbon;
use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Presenter;
use Ernestdefoe\Anthology\Series;
use Flarum\Http\RequestUtil;
use Flarum\Post\Exception\FloodingException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/anthology/series (create; a moderator may pass userId to start one for that member) and PATCH /api/anthology/series/{id} (title, description, status). */
class SaveSeriesController implements RequestHandlerInterface
{
    use Concerns;

    public const MAX_DESCRIPTION = 5000;
    public const PER_HOUR = 10;

    public function __construct(private Presenter $presenter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $data = $this->body($request);
        $creating = $request->getMethod() === 'POST';

        if ($creating) {
            if (! Access::canView($actor) || ! Access::canCreate($actor)) {
                throw new PermissionDeniedException();
            }
            // One indexed count, on creation only: enough series for any writer, not a flood.
            if (! Access::canModerate($actor)
                && Series::query()->where('user_id', $actor->id)->where('created_at', '>', Carbon::now()->subHour())->count() >= self::PER_HOUR) {
                throw new FloodingException();
            }
            // A moderator may start one for another member, to file that member's discussions under it.
            $owner = $actor;
            if (isset($data['userId']) && $this->id($data['userId']) !== $actor->id) {
                if (! Access::canModerate($actor)) {
                    throw new PermissionDeniedException();
                }
                $owner = User::query()->findOrFail($this->id($data['userId']));
            }
            $series = new Series();
            $series->user_id = $owner->id;
            $series->status = 'ongoing';
            $series->last_chapter_at = Carbon::now();
        } else {
            $series = $this->editableSeries($actor, $this->routeId($request));
        }

        if ($creating || array_key_exists('title', $data)) {
            $title = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['title'] ?? '')));
            if ($title === '') {
                $this->invalid('title', 'title_required');
            }
            if (mb_strlen($title) > 200) {
                $this->invalid('title', 'title_too_long');
            }
            $series->title = $title;
            $series->slug = Str::limit(Str::slug($title), 190, '');
        }

        if (array_key_exists('description', $data)) {
            $description = trim(str_replace("\r\n", "\n", (string) $data['description']));
            if (mb_strlen($description) > self::MAX_DESCRIPTION) {
                $this->invalid('description', 'description_too_long', ['{max}' => (string) self::MAX_DESCRIPTION]);
            }
            $series->description = $description === '' ? null : $description;
        }

        if (array_key_exists('status', $data)) {
            if (! in_array($data['status'], Series::STATUSES, true)) {
                $this->invalid('status', 'bad_status');
            }
            $series->status = $data['status'];
        }

        $series->save();

        if ($creating) {
            $owner->newQuery()->whereKey($owner->id)->increment('anthology_series_count');
            $series->setRelation('user', $owner);
        }

        return new JsonResponse(['data' => $this->presenter->card($series, $actor) + ['description' => $series->description]], $creating ? 201 : 200);
    }
}
