{{-- A new chapter: one translated sentence and a link. Titles and names are escaped before they reach the Markdown converter. --}}
<x-mail::html.notification>
    <x-slot:body>
        {!! $formatter->convert($translator->trans('ernestdefoe-anthology.email.new_chapter.body', [
            '{author}' => e($blueprint->discussion->user?->display_name ?? ''),
            '{series}' => e($blueprint->series->title),
            '{chapter}' => e($blueprint->discussion->title),
            '{url}' => $url->to('forum')->route('discussion', ['id' => $blueprint->discussion->id.'-'.$blueprint->discussion->slug]),
        ])) !!}
    </x-slot:body>
</x-mail::html.notification>
