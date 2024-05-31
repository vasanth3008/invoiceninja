@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ''])

@section('gateway_head')

@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="gateway_type_id" id="gateway_type_id" value="{{ $gateway_type_id }}">
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div id="paypal-button-container" class="paypal-button-container"></div>
   
@endsection

@section('gateway_footer')
@endsection

@push('footer')

<script src="https://www.paypal.com/sdk/js?client-id={!! $client_id !!}&currency={!! $currency !!}&components=buttons,funding-eligibility&intent=capture&enable-funding={!! $funding_source !!}"  data-partner-attribution-id="invoiceninja_SP_PPCP"></script>

<script>

//&buyer-country=US&currency=USD&enable-funding=venmo
    const fundingSource = "{!! $funding_source !!}";
    const clientId = "{{ $client_id }}";
    const orderId = "{!! $order_id !!}";
    const environment = "{{ $gateway->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production' }}";

    paypal.Buttons({
        env: environment,
        fundingSource: fundingSource,
        client: clientId,
        createOrder: function(data, actions) {
            return orderId;  
        },
        onApprove: function(data, actions) {

            console.log(data);
            
            document.getElementById("gateway_response").value =JSON.stringify( data );
            
            formData = JSON.stringify(Object.fromEntries(new FormData(document.getElementById("server_response")))),

            fetch('{{ route('client.payments.response') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData,
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json(); // or response.json() if the response is JSON
            })
            .then(data => {

                var errorDetail = Array.isArray(data.details) && data.details[0];

                if (errorDetail && ['INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED'].includes(errorDetail.issue)) {
                    return actions.restart();
                }

                if(data.redirect){
                    window.location.href = data.redirect;
                    return;
                }

                document.getElementById("gateway_response").value =JSON.stringify( data );
                document.getElementById("server_response").submit();
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('errors').textContent = `Sorry, your transaction could not be processed...\n\n${error.message}`;
                document.getElementById('errors').hidden = false;
            });



        },
        onCancel: function() {
            window.location.href = "/client/invoices/";
        },
        onError: function(error) {

            console.log("on error");
            console.log(error);

            document.getElementById("gateway_response").value = error;
            document.getElementById("server_response").submit();
        },
        onClick: function (){

            if(fundingSource != 'card')
              document.getElementById('paypal-button-container').hidden = true;

            document.querySelector('div[data-ref="required-fields-container').classList.add('hidden');
            
        },
        onInit: function (){
            console.log("init");
        }
    
    }).render('#paypal-button-container').catch(function(err) {
        
      document.getElementById('errors').textContent = err;
      document.getElementById('errors').hidden = false;
        
    });
    
    document.getElementById("server_response").addEventListener('submit', (e) => {
		if (document.getElementById("server_response").classList.contains('is-submitting')) {
			e.preventDefault();
		}
		
		document.getElementById("server_response").classList.add('is-submitting');
	});

</script>
@endpush