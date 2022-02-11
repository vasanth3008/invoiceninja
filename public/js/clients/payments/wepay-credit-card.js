/*! For license information please see wepay-credit-card.js.LICENSE.txt */
(()=>{var e;function t(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function n(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}var r=null===(e=document.querySelector('meta[name="wepay-action"]'))||void 0===e?void 0:e.content,d=function(){function e(){var n=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"payment";t(this,e),this.action=n,this.errors=document.getElementById("errors")}var r,d,a;return r=e,(d=[{key:"initializeWePay",value:function(){var e,t=null===(e=document.querySelector('meta[name="wepay-environment"]'))||void 0===e?void 0:e.content;return WePay.set_endpoint("staging"===t?"stage":"production"),this}},{key:"validateCreditCardFields",value:function(){return this.myCard=$("#my-card"),""===document.getElementById("cardholder_name")?(document.getElementById("cardholder_name").focus(),this.errors.textContent="Cardholder name required.",void(this.errors.hidden=!1)):""===this.myCard.CardJs("cardNumber").replace(/[^\d]/g,"")?(document.getElementById("card_number").focus(),this.errors.textContent="Card number required.",void(this.errors.hidden=!1)):""===this.myCard.CardJs("cvc").replace(/[^\d]/g,"")?(document.getElementById("cvv").focus(),this.errors.textContent="CVV number required.",void(this.errors.hidden=!1)):""===this.myCard.CardJs("expiryMonth").replace(/[^\d]/g,"")?(this.errors.textContent="Expiry Month number required.",void(this.errors.hidden=!1)):""!==this.myCard.CardJs("expiryYear").replace(/[^\d]/g,"")||(this.errors.textContent="Expiry Year number required.",void(this.errors.hidden=!1))}},{key:"handleAuthorization",value:function(){var e=this;if(this.validateCreditCardFields()){var t=document.getElementById("card_button");t.disabled=!0,t.querySelector("svg").classList.remove("hidden"),t.querySelector("span").classList.add("hidden"),WePay.credit_card.create({client_id:document.querySelector("meta[name=wepay-client-id]").content,user_name:document.getElementById("cardholder_name").value,email:document.querySelector("meta[name=contact-email]").content,cc_number:this.myCard.CardJs("cardNumber").replace(/[^\d]/g,""),cvv:this.myCard.CardJs("cvc").replace(/[^\d]/g,""),expiration_month:this.myCard.CardJs("expiryMonth").replace(/[^\d]/g,""),expiration_year:this.myCard.CardJs("expiryYear").replace(/[^\d]/g,""),address:{postal_code:document.querySelector(["meta[name=client-postal-code"]).content}},(function(n){n.error?((t=document.getElementById("card_button")).disabled=!1,t.querySelector("svg").classList.add("hidden"),t.querySelector("span").classList.remove("hidden"),e.errors.textContent="",e.errors.textContent=n.error_description,e.errors.hidden=!1):(document.querySelector('input[name="credit_card_id"]').value=n.credit_card_id,document.getElementById("server_response").submit())}))}}},{key:"completePaymentUsingToken",value:function(e){document.querySelector('input[name="credit_card_id"]').value=null,document.querySelector('input[name="token"]').value=e,document.getElementById("server-response").submit()}},{key:"completePaymentWithoutToken",value:function(){var e=this;if(!this.validateCreditCardFields())return document.getElementById("pay-now").disabled=!1,document.querySelector("#pay-now > svg").classList.add("hidden"),void document.querySelector("#pay-now > span").classList.remove("hidden");WePay.credit_card.create({client_id:document.querySelector("meta[name=wepay-client-id]").content,user_name:document.getElementById("cardholder_name").value,email:document.querySelector("meta[name=contact-email]").content,cc_number:this.myCard.CardJs("cardNumber").replace(/[^\d]/g,""),cvv:this.myCard.CardJs("cvc").replace(/[^\d]/g,""),expiration_month:this.myCard.CardJs("expiryMonth").replace(/[^\d]/g,""),expiration_year:this.myCard.CardJs("expiryYear").replace(/[^\d]/g,""),address:{postal_code:document.querySelector(["meta[name=client-postal-code"]).content}},(function(t){t.error?(e.payNowButton.disabled=!1,e.payNowButton.querySelector("svg").classList.add("hidden"),e.payNowButton.querySelector("span").classList.remove("hidden"),e.errors.textContent="",e.errors.textContent=t.error_description,e.errors.hidden=!1):(document.querySelector('input[name="credit_card_id"]').value=t.credit_card_id,document.querySelector('input[name="token"]').value=null,document.getElementById("server-response").submit())}))}},{key:"handle",value:function(){var e=this;this.initializeWePay(),"authorize"===this.action?document.getElementById("card_button").addEventListener("click",(function(){return e.handleAuthorization()})):"payment"===this.action&&(Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach((function(e){return e.addEventListener("click",(function(e){document.getElementById("save-card--container").style.display="none",document.getElementById("wepay--credit-card-container").style.display="none",document.getElementById("token").value=e.target.dataset.token}))})),document.getElementById("toggle-payment-with-credit-card").addEventListener("click",(function(e){document.getElementById("save-card--container").style.display="grid",document.getElementById("wepay--credit-card-container").style.display="flex",document.getElementById("token").value=null})),document.getElementById("pay-now").addEventListener("click",(function(){e.payNowButton=document.getElementById("pay-now"),e.payNowButton.disabled=!0,e.payNowButton.querySelector("svg").classList.remove("hidden"),e.payNowButton.querySelector("span").classList.add("hidden");var t=document.querySelector("input[name=token]"),n=document.querySelector("input[name=token-billing-checkbox]:checked");return n&&(document.getElementById("store_card").value=n.value),t.value?e.completePaymentUsingToken(t.value):e.completePaymentWithoutToken()})))}}])&&n(r.prototype,d),a&&n(r,a),e}();new d(r).handle()})();