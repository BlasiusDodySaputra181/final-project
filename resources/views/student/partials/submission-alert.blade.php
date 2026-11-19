@if (count($messages) > 0)
    <div
        class="submission-alert submission-alert--{{ $isSuccess ? 'success' : 'error' }}"
        role="{{ $isSuccess ? 'status' : 'alert' }}"
    >
        <div class="submission-alert__icon" aria-hidden="true">
            <i class="fa-solid {{ $isSuccess ? 'fa-circle-check' : 'fa-circle-exclamation' }}"></i>
        </div>

        <div class="submission-alert__body">
            <strong class="submission-alert__title">{{ $title }}</strong>

            @if (count($messages) === 1)
                <p class="submission-alert__text">{{ $messages[0] }}</p>
            @else
                <ul class="submission-alert__list">
                    @foreach ($messages as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
