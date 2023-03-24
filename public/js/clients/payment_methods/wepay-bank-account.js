/*! For license information please see wepay-bank-account.js.LICENSE.txt */
(()=>{function e(t){return e="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},e(t)}function t(t,n){for(var o=0;o<n.length;o++){var r=n[o];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(t,(i=r.key,a=void 0,a=function(t,n){if("object"!==e(t)||null===t)return t;var o=t[Symbol.toPrimitive];if(void 0!==o){var r=o.call(t,n||"default");if("object"!==e(r))return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===n?String:Number)(t)}(i,"string"),"symbol"===e(a)?a:String(a)),r)}var i,a}var n=function(){function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e)}var n,o,r;return n=e,(o=[{key:"initializeWePay",value:function(){var e,t=null===(e=document.querySelector('meta[name="wepay-environment"]'))||void 0===e?void 0:e.content;return WePay.set_endpoint("staging"===t?"stage":"production"),this}},{key:"showBankPopup",value:function(){var e,t;WePay.bank_account.create({client_id:null===(e=document.querySelector("meta[name=wepay-client-id]"))||void 0===e?void 0:e.content,email:null===(t=document.querySelector("meta[name=contact-email]"))||void 0===t?void 0:t.content,options:{avoidMicrodeposits:!0}},(function(e){e.error?(errors.textContent="",errors.textContent=e.error_description,errors.hidden=!1):(document.querySelector('input[name="bank_account_id"]').value=e.bank_account_id,document.getElementById("server_response").submit())}),(function(e){e.error&&(errors.textContent="",errors.textContent=e.error_description,errors.hidden=!1)}))}},{key:"handle",value:function(){this.initializeWePay().showBankPopup()}}])&&t(n.prototype,o),r&&t(n,r),Object.defineProperty(n,"prototype",{writable:!1}),e}();document.addEventListener("DOMContentLoaded",(function(){(new n).handle()}))})();