/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class ProcessACSS {
    constructor(key, stripeConnect) {
        this.key = key;
        this.errors = document.getElementById('errors');
        this.stripeConnect = stripeConnect;
    }

    setupStripe = () => {

        if (this.stripeConnect){
           // this.stripe.stripeAccount = this.stripeConnect;
           
           this.stripe = Stripe(this.key, {
              stripeAccount: this.stripeConnect,
            }); 
           
        }
        else {
            this.stripe = Stripe(this.key);
        }

        return this;
    };

    handle = () => {
        document.getElementById('pay-now').addEventListener('click', (e) => {

        let errors = document.getElementById('errors');

        if (document.getElementById('acss-name').value === "") {
            document.getElementById('acss-name').focus();
            errors.textContent = document.querySelector('meta[name=translation-name-required]').content;
            errors.hidden = false;
            return;
        }

        if (document.getElementById('acss-email-address').value === "") {
            document.getElementById('acss-email-address').focus();
            errors.textContent = document.querySelector('meta[name=translation-email-required]').content;
            errors.hidden = false;
            return ;
        }

            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.remove('hidden');
            document.querySelector('#pay-now > span').classList.add('hidden');

            this.stripe.confirmAcssDebitPayment(
                document.querySelector('meta[name=pi-client-secret').content,
                {
                    payment_method: {
                        billing_details: {
                            name: document.getElementById("acss-name").value,
                            email: document.getElementById("acss-email-address").value,
                        },
                    },
                }
            ).then((result) => {
                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                return this.handleSuccess(result);
            });
        });
    };

    handleSuccess(result) {
        document.querySelector(
            'input[name="gateway_response"]'
        ).value = JSON.stringify(result.paymentIntent);

        document.getElementById('server-response').submit();
    }

    handleFailure(message) {
        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = message;
        errors.hidden = false;

            document.getElementById('pay-now').disabled = false;
            document.querySelector('#pay-now > svg').classList.add('hidden');
            document.querySelector('#pay-now > span').classList.remove('hidden');
    }
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
)?.content ?? '';

const stripeConnect =
    document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

new ProcessACSS(publishableKey, stripeConnect).setupStripe().handle();
