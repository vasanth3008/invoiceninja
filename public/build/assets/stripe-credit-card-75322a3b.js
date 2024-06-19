/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class l{constructor(e,t,n,d){this.key=e,this.secret=t,this.onlyAuthorization=n,this.stripeConnect=d}setupStripe(){return this.stripeConnect?this.stripe=Stripe(this.key,{stripeAccount:this.stripeConnect}):this.stripe=Stripe(this.key),this.elements=this.stripe.elements(),this}createElement(){var e;return this.cardElement=this.elements.create("card",{hidePostalCode:((e=document.querySelector("meta[name=stripe-require-postal-code]"))==null?void 0:e.content)==="0",value:{postalCode:document.querySelector("meta[name=client-postal-code]").content},hideIcon:!1}),this}mountCardElement(){return this.cardElement.mount("#card-element"),this}completePaymentUsingToken(){let e=document.querySelector("input[name=token]").value,t=document.getElementById("pay-now");this.payNowButton=t,this.payNowButton.disabled=!0,this.payNowButton.querySelector("svg").classList.remove("hidden"),this.payNowButton.querySelector("span").classList.add("hidden"),this.stripe.handleCardPayment(this.secret,{payment_method:e}).then(n=>n.error?this.handleFailure(n.error.message):this.handleSuccess(n))}completePaymentWithoutToken(){let e=document.getElementById("pay-now");this.payNowButton=e,this.payNowButton.disabled=!0,this.payNowButton.querySelector("svg").classList.remove("hidden"),this.payNowButton.querySelector("span").classList.add("hidden");let t=document.getElementById("cardholder-name");this.stripe.handleCardPayment(this.secret,this.cardElement,{payment_method_data:{billing_details:{name:t.value}}}).then(n=>n.error?this.handleFailure(n.error.message):this.handleSuccess(n))}handleSuccess(e){document.querySelector('input[name="gateway_response"]').value=JSON.stringify(e.paymentIntent);let t=document.querySelector('input[name="token-billing-checkbox"]:checked');t&&(document.querySelector('input[name="store_card"]').value=t.value),document.getElementById("server-response").submit()}handleFailure(e){let t=document.getElementById("errors");t.textContent="",t.textContent=e,t.hidden=!1,this.payNowButton.disabled=!1,this.payNowButton.querySelector("svg").classList.add("hidden"),this.payNowButton.querySelector("span").classList.remove("hidden")}handleAuthorization(){let e=document.getElementById("cardholder-name"),t=document.getElementById("authorize-card");this.payNowButton=t,this.payNowButton.disabled=!0,this.payNowButton.querySelector("svg").classList.remove("hidden"),this.payNowButton.querySelector("span").classList.add("hidden"),this.stripe.handleCardSetup(this.secret,this.cardElement,{payment_method_data:{billing_details:{name:e.value}}}).then(n=>n.error?this.handleFailure(n.error.message):this.handleSuccessfulAuthorization(n))}handleSuccessfulAuthorization(e){document.getElementById("gateway_response").value=JSON.stringify(e.setupIntent),document.getElementById("server_response").submit()}handle(){this.setupStripe(),this.onlyAuthorization?(this.createElement().mountCardElement(),document.getElementById("authorize-card").addEventListener("click",()=>this.handleAuthorization())):(Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach(e=>e.addEventListener("click",t=>{document.getElementById("stripe--payment-container").classList.add("hidden"),document.getElementById("save-card--container").style.display="none",document.querySelector("input[name=token]").value=t.target.dataset.token})),document.getElementById("toggle-payment-with-credit-card").addEventListener("click",e=>{document.getElementById("stripe--payment-container").classList.remove("hidden"),document.getElementById("save-card--container").style.display="grid",document.querySelector("input[name=token]").value=""}),this.createElement().mountCardElement(),document.getElementById("pay-now").addEventListener("click",()=>{try{return document.querySelector("input[name=token]").value?this.completePaymentUsingToken():this.completePaymentWithoutToken()}catch(e){console.log(e.message)}}))}}var o;const c=((o=document.querySelector('meta[name="stripe-publishable-key"]'))==null?void 0:o.content)??"";var r;const u=((r=document.querySelector('meta[name="stripe-secret"]'))==null?void 0:r.content)??"";var a;const m=((a=document.querySelector('meta[name="only-authorization"]'))==null?void 0:a.content)??"";var s;const h=((s=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:s.content)??"";let i=new l(c,u,m,h);i.handle();document.addEventListener("livewire:init",()=>{Livewire.on("passed-required-fields-check",()=>i.handle())});
