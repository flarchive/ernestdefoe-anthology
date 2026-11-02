<?php

namespace Ernestdefoe\Anthology\Notification;

use Ernestdefoe\Anthology\Series;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/**
 * For followers: "New chapter in {series}". The subject is the chapter's
 * discussion, so the alert opens it, and Flarum drops the alert for anyone who
 * can no longer see it.
 */
class NewChapterBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public Discussion $discussion, public Series $series)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->discussion->user;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->discussion;
    }

    public function getData(): array
    {
        return ['seriesId' => $this->series->id, 'seriesTitle' => $this->series->title];
    }

    public static function getType(): string
    {
        return 'anthologyNewChapter';
    }

    public static function getSubjectModel(): string
    {
        return Discussion::class;
    }

    public function getEmailViews(): array
    {
        return ['text' => 'ernestdefoe-anthology::emails.plain.notification', 'html' => 'ernestdefoe-anthology::emails.html.notification'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ernestdefoe-anthology.email.new_chapter.subject', ['{series}' => $this->series->title]);
    }
}
