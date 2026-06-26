<section class="store-contact" id="contact">
    <div class="store-contact__inner">
        @if (session('contact_sent'))
            <p class="store-contact__success">Thank you. Your message has been received.</p>
        @endif

        <form class="store-contact__form" method="post" action="{{ route('store.contact') }}">
            @csrf
            <input type="text" name="name" class="store-contact__input" placeholder="Your name" value="{{ old('name') }}" required />
            <input type="email" name="email" class="store-contact__input" placeholder="Email address" value="{{ old('email') }}" required />
            <textarea name="message" class="store-contact__input store-contact__input--message" placeholder="Your message" required>{{ old('message') }}</textarea>
            <button type="submit" class="store-contact__submit">Submit</button>
        </form>
    </div>
</section>
