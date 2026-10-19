{{--
  List of repeatable items for one section.
  Vars: section, noun (e.g. "service"), items (collection), hasBody, hasIcon, hasImage, isFaq
--}}
@php
    $list     = $items[$section] ?? collect();
    $hasBody  = $hasBody  ?? true;
    $hasIcon  = $hasIcon  ?? true;
    $hasImage = $hasImage ?? false;
    $isFaq    = $isFaq    ?? false;
@endphp
<div class="ws-card">
    <div class="ws-card-head">
        <div class="ws-card-title">
            <span class="ws-card-icon"><i class='bx bx-list-ul'></i></span>
            <h3>{{ ucfirst($noun) }}s</h3>
            <span class="ws-count">{{ $list->count() }}</span>
        </div>
        <button type="button" class="ws-btn ws-btn-primary js-ws-add"
                data-section="{{ $section }}" data-noun="{{ $noun }}"
                data-has-body="{{ $hasBody ? 1 : 0 }}" data-has-icon="{{ $hasIcon ? 1 : 0 }}"
                data-has-image="{{ $hasImage ? 1 : 0 }}" data-is-faq="{{ $isFaq ? 1 : 0 }}"
                data-store="{{ route('admin.website.items.store') }}">
            <i class='bx bx-plus'></i> Add {{ $noun }}
        </button>
    </div>

    @if($list->isEmpty())
        <div class="ws-empty"><i class='bx bx-list-plus'></i><p>Nothing here yet. Click “Add {{ $noun }}”.</p></div>
    @else
    <ul class="ws-list">
        @foreach($list as $item)
        <li class="ws-row {{ $item->is_active ? '' : 'is-hidden' }}">
            <div class="ws-row-thumb">
                @if($hasImage && $item->image_url)
                    <img src="{{ $item->image_url }}" alt="">
                @elseif($item->icon)
                    <i class='bx {{ $item->icon }}'></i>
                @else
                    <i class='bx bx-help-circle'></i>
                @endif
            </div>
            <div class="ws-row-main">
                <strong>{{ $item->title }}</strong>
                @if($isFaq)<span class="ws-tag">{{ $faqCategories[$item->category] ?? $item->category }}</span>@endif
                @if($hasBody)<p>{{ \Illuminate\Support\Str::limit(str_replace('**', '', (string) $item->body), 110) }}</p>@endif
            </div>
            <span class="ws-badge {{ $item->is_active ? 'is-on' : 'is-off' }}"><i class='bx {{ $item->is_active ? 'bx-show' : 'bx-hide' }}'></i> {{ $item->is_active ? 'Visible' : 'Hidden' }}</span>
            <div class="ws-row-actions">
                <form method="POST" action="{{ route('admin.website.items.move', [$item, 'up']) }}">@csrf
                    <button class="ws-icon-btn" title="Move up" aria-label="Move up" @disabled($loop->first)><i class='bx bx-chevron-up'></i></button>
                </form>
                <form method="POST" action="{{ route('admin.website.items.move', [$item, 'down']) }}">@csrf
                    <button class="ws-icon-btn" title="Move down" aria-label="Move down" @disabled($loop->last)><i class='bx bx-chevron-down'></i></button>
                </form>
                <form method="POST" action="{{ route('admin.website.items.toggle', $item) }}">@csrf
                    <button class="ws-icon-btn" title="{{ $item->is_active ? 'Hide' : 'Show' }}" aria-label="{{ $item->is_active ? 'Hide' : 'Show' }}">
                        <i class='bx {{ $item->is_active ? 'bx-hide' : 'bx-show' }}'></i>
                    </button>
                </form>
                <button type="button" class="ws-icon-btn js-ws-edit" title="Edit" aria-label="Edit"
                        data-section="{{ $section }}" data-noun="{{ $noun }}"
                        data-has-body="{{ $hasBody ? 1 : 0 }}" data-has-icon="{{ $hasIcon ? 1 : 0 }}"
                        data-has-image="{{ $hasImage ? 1 : 0 }}" data-is-faq="{{ $isFaq ? 1 : 0 }}"
                        data-update="{{ route('admin.website.items.update', $item) }}"
                        data-item="{{ json_encode([
                            'title' => $item->title, 'body' => $item->body, 'icon' => $item->icon,
                            'category' => $item->category, 'active' => $item->is_active,
                            'image' => $item->image_url,
                        ]) }}"><i class='bx bx-edit'></i></button>
                <form method="POST" action="{{ route('admin.website.items.destroy', $item) }}"
                      data-confirm="Move “{{ $item->title }}” to the Archive? It will be hidden from the website, and you can restore it any time.">@csrf @method('DELETE')
                    <button class="ws-icon-btn is-danger" title="Archive" aria-label="Archive"><i class='bx bx-archive'></i></button>
                </form>
            </div>
        </li>
        @endforeach
    </ul>
    @endif
</div>