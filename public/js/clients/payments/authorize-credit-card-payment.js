/*! For license information please see authorize-credit-card-payment.js.LICENSE.txt */
(()=>{function e(e,t){for(var n=0;n<t.length;n++){var a=t[n];a.enumerable=a.enumerable||!1,a.configurable=!0,"value"in a&&(a.writable=!0),Object.defineProperty(e,a.key,a)}}function t(e,t,n){return t in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}var n=function(){function n(e,a){var r=this;!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,n),t(this,"handleAuthorization",(function(){var e=$("#my-card"),t={};t.clientKey=r.publicKey,t.apiLoginID=r.loginId;var n={};n.cardNumber=e.CardJs("cardNumber").replace(/[^\d]/g,""),n.month=e.CardJs("expiryMonth").replace(/[^\d]/g,""),n.year=e.CardJs("expiryYear").replace(/[^\d]/g,""),n.cardCode=document.getElementById("cvv").value.replace(/[^\d]/g,"");var a={};return a.authData=t,a.cardData=n,document.getElementById("pay-now")&&(document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden")),Accept.dispatchData(a,r.responseHandler),!1})),t(this,"responseHandler",(function(e){if("Error"===e.messages.resultCode){$("#errors").show().html("<p>"+e.messages.message[0].code+": "+e.messages.message[0].text+"</p>"),document.getElementById("pay-now").disabled=!1,document.querySelector("#pay-now > svg").classList.add("hidden"),document.querySelector("#pay-now > span").classList.remove("hidden")}else if("Ok"===e.messages.resultCode){document.getElementById("dataDescriptor").value=e.opaqueData.dataDescriptor,document.getElementById("dataValue").value=e.opaqueData.dataValue;var t=document.querySelector("input[name=token-billing-checkbox]:checked");t&&(document.getElementById("store_card").value=t.value),document.getElementById("server_response").submit()}return!1})),t(this,"handle",(function(){Array.from(document.getElementsByClassName("toggle-payment-with-token")).forEach((function(e){return e.addEventListener("click",(function(e){document.getElementById("save-card--container").style.display="none",document.getElementById("authorize--credit-card-container").style.display="none",document.getElementById("token").value=e.target.dataset.token}))}));var e=document.getElementById("toggle-payment-with-credit-card");e&&e.addEventListener("click",(function(){document.getElementById("save-card--container").style.display="grid",document.getElementById("authorize--credit-card-container").style.display="flex",document.getElementById("token").value=null}));var t=document.getElementById("pay-now");return t&&t.addEventListener("click",(function(e){var t=document.getElementById("token");t.value?r.handlePayNowAction(t.value):r.handleAuthorization()})),r})),this.publicKey=e,this.loginId=a,this.cardHolderName=document.getElementById("cardholder_name")}var a,r,o;return a=n,(r=[{key:"handlePayNowAction",value:function(e){document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden"),document.getElementById("token").value=e,document.getElementById("server_response").submit()}}])&&e(a.prototype,r),o&&e(a,o),Object.defineProperty(a,"prototype",{writable:!1}),n}();new n(document.querySelector('meta[name="authorize-public-key"]').content,document.querySelector('meta[name="authorize-login-id"]').content).handle()})();