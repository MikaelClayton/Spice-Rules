@php
    $unread = (int) ($chat['unread'] ?? 0);
    $mentions = (int) ($chat['mentions'] ?? 0);
    $canSend = (bool) ($chat['can_send'] ?? false);
@endphp

<div
    class="group/chat fixed right-4 bottom-4 z-40 flex flex-col items-end gap-3 max-sm:data-[open]:inset-0 max-sm:data-[open]:right-auto max-sm:data-[open]:bottom-auto max-sm:data-[open]:items-stretch"
    data-pub-golf-chat
    data-poll-url="{{ route('pub-golf.chat.index', $crawl) }}"
    data-store-url="{{ route('pub-golf.chat.store', $crawl) }}"
    data-read-url="{{ route('pub-golf.chat.read', $crawl) }}"
>
    <script type="application/json" data-chat-state>@json($chat)</script>

    <button
        type="button"
        class="absolute inset-0 z-0 hidden bg-neutral/50 sm:hidden"
        data-chat-backdrop
        aria-label="Close chat"
    ></button>

    <section
        class="relative z-10 hidden w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-2xl max-sm:mt-auto max-sm:h-[min(40rem,100%)] max-sm:w-full max-sm:rounded-b-none max-sm:rounded-t-3xl sm:max-h-[min(36rem,calc(100dvh-6rem))]"
        data-chat-panel
        aria-label="Crawl chat"
    >
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-base-300 px-4 py-3">
            <div>
                <h2 class="font-semibold">Crawl chat</h2>
                <p class="text-xs text-base-content/60">@ to ping someone</p>
            </div>
            <button type="button" class="btn btn-ghost btn-sm btn-circle" data-chat-close aria-label="Close chat">×</button>
        </div>

        <div class="min-h-48 flex-1 space-y-3 overflow-y-auto px-3 py-3 sm:min-h-0 sm:max-h-[min(22rem,50vh)]" data-chat-messages>
            @forelse ($chat['messages'] as $message)
                <article class="chat {{ $message['is_you'] ? 'chat-end' : 'chat-start' }}">
                    <div class="chat-header text-xs">
                        {{ $message['name'] }}
                        <time class="opacity-50">{{ $message['created_at'] }}</time>
                    </div>
                    <div class="chat-bubble {{ $message['mentioned_you'] ? 'chat-bubble-secondary' : '' }} whitespace-pre-wrap break-words">
                        @if ($message['photo_url'])
                            <img src="{{ $message['photo_url'] }}" alt="" class="mb-2 max-h-48 w-full rounded-xl object-cover">
                        @endif
                        @if ($message['body'])
                            {{ $message['body'] }}
                        @endif
                    </div>
                </article>
            @empty
                <p class="px-1 py-6 text-center text-sm text-base-content/60" data-chat-empty>No messages yet. Say hi.</p>
            @endforelse
        </div>

        @if ($canSend)
            <form
                method="POST"
                action="{{ route('pub-golf.chat.store', $crawl) }}"
                enctype="multipart/form-data"
                class="relative shrink-0 border-t border-base-300 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
                data-chat-form
                data-ajax
            >
                @csrf
                <p class="hidden pb-2 text-sm text-error" data-chat-error></p>
                <ul class="menu menu-sm absolute inset-x-3 bottom-full z-10 mb-1 hidden rounded-box border border-base-300 bg-base-100 shadow-lg" data-chat-mentions-menu></ul>
                <div class="flex items-end gap-2">
                    <label class="btn btn-ghost btn-square shrink-0 sm:btn-sm" title="Add a photo">
                        <input
                            type="file"
                            name="photo"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            class="hidden"
                            data-chat-photo
                        >
                        <span aria-hidden="true">📷</span>
                        <span class="sr-only">Add a photo</span>
                    </label>
                    <textarea
                        name="body"
                        rows="1"
                        maxlength="1000"
                        class="textarea textarea-bordered min-h-11 flex-1 resize-none text-base sm:min-h-10 sm:text-sm"
                        placeholder="Message the crawl"
                        data-chat-input
                    >{{ old('body') }}</textarea>
                    <button type="submit" class="btn btn-primary shrink-0 sm:btn-sm">Send</button>
                </div>
                <p class="hidden pt-1 text-xs text-base-content/60" data-chat-photo-name></p>
            </form>
        @else
            <p class="shrink-0 border-t border-base-300 px-4 py-3 text-sm text-base-content/60">This crawl has wrapped, so chat is read only.</p>
        @endif
    </section>

    <button
        type="button"
        class="relative z-10 btn btn-primary btn-circle h-14 w-14 shadow-xl group-data-[open]/chat:max-sm:hidden"
        data-chat-toggle
        aria-expanded="false"
        aria-label="Open crawl chat"
    >
        <span class="text-xl" aria-hidden="true">💬</span>
        <span
            class="badge badge-secondary absolute -top-1 -left-1 min-w-6 px-1 {{ $unread > 0 ? '' : 'hidden' }}"
            data-chat-unread
        >{{ $unread }}</span>
        <span
            class="badge badge-accent absolute -top-1 -right-1 min-w-6 px-1 {{ $mentions > 0 ? '' : 'hidden' }}"
            data-chat-mentions
        >{{ '@'.$mentions }}</span>
    </button>
</div>
