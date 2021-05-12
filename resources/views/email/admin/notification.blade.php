@component('email.template.master', ['design' => 'light', 'settings' => $settings])

    @slot('header')
        @include('email.components.header', ['logo' => $logo])
    @endslot

    <h2>{!! $title !!}</h2>

    <p>{!! $body !!}</p>

    @if(isset($whitelabel) && !$whitelabel)
        @slot('footer')
            @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
                For any info, please visit InvoiceNinja.
            @endcomponent
        @endslot
    @endif
@endcomponent
