/*! For license information please see accept.js.LICENSE.txt */
(()=>{function e(t){return e="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},e(t)}function t(t,n){for(var r=0;r<n.length;r++){var o=n[r];o.enumerable=o.enumerable||!1,o.configurable=!0,"value"in o&&(o.writable=!0),Object.defineProperty(t,(i=o.key,u=void 0,u=function(t,n){if("object"!==e(t)||null===t)return t;var r=t[Symbol.toPrimitive];if(void 0!==r){var o=r.call(t,n||"default");if("object"!==e(o))return o;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===n?String:Number)(t)}(i,"string"),"symbol"===e(u)?u:String(u)),o)}var i,u}var n=function(){function e(t,n){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),this.shouldDisplaySignature=t,this.shouldDisplayTerms=n,this.termsAccepted=!1}var n,r,o;return n=e,(r=[{key:"submitForm",value:function(){document.getElementById("approve-form").submit()}},{key:"displaySignature",value:function(){document.getElementById("displaySignatureModal").removeAttribute("style");var e=new SignaturePad(document.getElementById("signature-pad"),{penColor:"rgb(0, 0, 0)"});e.onEnd=function(){document.getElementById("signature-next-step").disabled=!1},this.signaturePad=e}},{key:"displayTerms",value:function(){document.getElementById("displayTermsModal").removeAttribute("style")}},{key:"handle",value:function(){var e=this;document.getElementById("signature-next-step").disabled=!0,document.getElementById("approve-button").addEventListener("click",(function(){e.shouldDisplaySignature&&e.shouldDisplayTerms&&(e.displaySignature(),document.getElementById("signature-next-step").addEventListener("click",(function(){e.displayTerms(),document.getElementById("accept-terms-button").addEventListener("click",(function(){document.querySelector('input[name="signature"').value=e.signaturePad.toDataURL(),e.termsAccepted=!0,e.submitForm()}))}))),e.shouldDisplaySignature&&!e.shouldDisplayTerms&&(e.displaySignature(),document.getElementById("signature-next-step").addEventListener("click",(function(){document.querySelector('input[name="signature"').value=e.signaturePad.toDataURL(),e.submitForm()}))),!e.shouldDisplaySignature&&e.shouldDisplayTerms&&(e.displayTerms(),document.getElementById("accept-terms-button").addEventListener("click",(function(){e.termsAccepted=!0,e.submitForm()}))),e.shouldDisplaySignature||e.shouldDisplayTerms||e.submitForm()}))}}])&&t(n.prototype,r),o&&t(n,o),Object.defineProperty(n,"prototype",{writable:!1}),e}(),r=document.querySelector('meta[name="require-purchase_order-signature"]').content,o=document.querySelector('meta[name="show-purchase_order-terms"]').content;new n(Boolean(+r),Boolean(+o)).handle()})();