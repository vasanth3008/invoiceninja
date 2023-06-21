@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <link
      rel="stylesheet"
      type="text/css"
      href="https://www.paypalobjects.com/webstatic/en_US/developer/docs/css/cardfields.css"
    />

@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

<div id="paypal-button-container" class="paypal-button-container"></div>
   
@endsection

@section('gateway_footer')
@endsection

@push('footer')
<script src="https://www.paypal.com/sdk/js?components=buttons,hosted-fields&intent=capture&client-id={!! $client_id !!}" data-client-token="{!! $token !!}">
</script>

<script>

    paypal.Buttons({ 
    
    env: "{{ $gateway->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production' }}",
    client: {
        @if($gateway->company_gateway->getConfigField('testMode'))
        sandbox: "{{ $gateway->company_gateway->getConfigField('clientId') }}"
        @else
        production: "{{ $gateway->company_gateway->getConfigField('clientId') }}"
        @endif
    },       
    createOrder: function(data, actions) {
      return "{!! $order_id !!}"  
    },
    onApprove: function(data, actions) {

      return actions.order.capture().then(function(details) {                                    
        
          document.getElementById("gateway_response").value =JSON.stringify( details );
          document.getElementById("server_response").submit();

      });           



      }
    });


    var FUNDING_SOURCES = [
      paypal.FUNDING.PAYPAL,
      paypal.FUNDING.CARD,
      paypal.FUNDING.VENMO,
      paypal.FUNDING.SEPA,
      paypal.FUNDING.BANCONTACT,
      paypal.FUNDING.EPS,
      paypal.FUNDING.GIROPAY,
      paypal.FUNDING.IDEAL,
      paypal.FUNDING.MERCADOPAGO,
      paypal.FUNDING.MYBANK,
      paypal.FUNDING.PAYLATER,
      paypal.FUNDING.P24,
      paypal.FUNDING.SOFORT,
    ];
    
    // Loop over each funding source
    FUNDING_SOURCES.forEach(function (fundingSource) {
    // Initialize the buttons
    var button = paypal.Buttons({
    fundingSource: fundingSource,
    })
    // Check if the button is eligible
    if (button.isEligible()) {
    // Render the standalone button for that funding source
    button.render('#paypal-button-container')
    }
    })


</script>
@endpush