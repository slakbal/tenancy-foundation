<div class="fi-simple-page">
    <div class="fi-simple-page-content">
        <header class="fi-simple-header">
            <h1 class="fi-simple-header-heading">{{ __('Get in touch') }}</h1>
            <p class="fi-simple-header-subheading">{{ __('Fill out the form below and we will get back to you.') }}</p>
        </header>

        {{ $this->content }}
    </div>

    <x-filament-actions::modals />
</div>
