<x-mail::plain.notification>
<x-slot:body>
{!! $translator->trans('ernestdefoe-anthology.email.new_chapter.body', [
    '{author}' => $blueprint->discussion->user?->display_name ?? '',
    '{series}' => $blueprint->series->title,
    '{chapter}' => $blueprint->discussion->title,
    '{url}' => $url->to('forum')->route('discussion', ['id' => $blueprint->discussion->id.'-'.$blueprint->discussion->slug]),
]) !!}
</x-slot:body>
</x-mail::plain.notification>
