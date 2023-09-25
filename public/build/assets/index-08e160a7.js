import{g as Ge}from"./_commonjsHelpers-725317a4.js";var ne={exports:{}},Be=function(r,n){return function(){for(var t=new Array(arguments.length),a=0;a<t.length;a++)t[a]=arguments[a];return r.apply(n,t)}},Ye=Be,w=Object.prototype.toString;function ie(e){return Array.isArray(e)}function re(e){return typeof e>"u"}function Qe(e){return e!==null&&!re(e)&&e.constructor!==null&&!re(e.constructor)&&typeof e.constructor.isBuffer=="function"&&e.constructor.isBuffer(e)}function De(e){return w.call(e)==="[object ArrayBuffer]"}function Ze(e){return w.call(e)==="[object FormData]"}function er(e){var r;return typeof ArrayBuffer<"u"&&ArrayBuffer.isView?r=ArrayBuffer.isView(e):r=e&&e.buffer&&De(e.buffer),r}function rr(e){return typeof e=="string"}function tr(e){return typeof e=="number"}function je(e){return e!==null&&typeof e=="object"}function T(e){if(w.call(e)!=="[object Object]")return!1;var r=Object.getPrototypeOf(e);return r===null||r===Object.prototype}function nr(e){return w.call(e)==="[object Date]"}function ir(e){return w.call(e)==="[object File]"}function ar(e){return w.call(e)==="[object Blob]"}function $e(e){return w.call(e)==="[object Function]"}function sr(e){return je(e)&&$e(e.pipe)}function or(e){return w.call(e)==="[object URLSearchParams]"}function ur(e){return e.trim?e.trim():e.replace(/^\s+|\s+$/g,"")}function fr(){return typeof navigator<"u"&&(navigator.product==="ReactNative"||navigator.product==="NativeScript"||navigator.product==="NS")?!1:typeof window<"u"&&typeof document<"u"}function ae(e,r){if(!(e===null||typeof e>"u"))if(typeof e!="object"&&(e=[e]),ie(e))for(var n=0,i=e.length;n<i;n++)r.call(null,e[n],n,e);else for(var t in e)Object.prototype.hasOwnProperty.call(e,t)&&r.call(null,e[t],t,e)}function te(){var e={};function r(t,a){T(e[a])&&T(t)?e[a]=te(e[a],t):T(t)?e[a]=te({},t):ie(t)?e[a]=t.slice():e[a]=t}for(var n=0,i=arguments.length;n<i;n++)ae(arguments[n],r);return e}function lr(e,r,n){return ae(r,function(t,a){n&&typeof t=="function"?e[a]=Ye(t,n):e[a]=t}),e}function cr(e){return e.charCodeAt(0)===65279&&(e=e.slice(1)),e}var p={isArray:ie,isArrayBuffer:De,isBuffer:Qe,isFormData:Ze,isArrayBufferView:er,isString:rr,isNumber:tr,isObject:je,isPlainObject:T,isUndefined:re,isDate:nr,isFile:ir,isBlob:ar,isFunction:$e,isStream:sr,isURLSearchParams:or,isStandardBrowserEnv:fr,forEach:ae,merge:te,extend:lr,trim:ur,stripBOM:cr},x=p;function ce(e){return encodeURIComponent(e).replace(/%3A/gi,":").replace(/%24/g,"$").replace(/%2C/gi,",").replace(/%20/g,"+").replace(/%5B/gi,"[").replace(/%5D/gi,"]")}var _e=function(r,n,i){if(!n)return r;var t;if(i)t=i(n);else if(x.isURLSearchParams(n))t=n.toString();else{var a=[];x.forEach(n,function(l,f){l===null||typeof l>"u"||(x.isArray(l)?f=f+"[]":l=[l],x.forEach(l,function(s){x.isDate(s)?s=s.toISOString():x.isObject(s)&&(s=JSON.stringify(s)),a.push(ce(f)+"="+ce(s))}))}),t=a.join("&")}if(t){var u=r.indexOf("#");u!==-1&&(r=r.slice(0,u)),r+=(r.indexOf("?")===-1?"?":"&")+t}return r},dr=p;function U(){this.handlers=[]}U.prototype.use=function(r,n,i){return this.handlers.push({fulfilled:r,rejected:n,synchronous:i?i.synchronous:!1,runWhen:i?i.runWhen:null}),this.handlers.length-1};U.prototype.eject=function(r){this.handlers[r]&&(this.handlers[r]=null)};U.prototype.forEach=function(r){dr.forEach(this.handlers,function(i){i!==null&&r(i)})};var hr=U,pr=p,mr=function(r,n){pr.forEach(r,function(t,a){a!==n&&a.toUpperCase()===n.toUpperCase()&&(r[n]=t,delete r[a])})},Ie=function(r,n,i,t,a){return r.config=n,i&&(r.code=i),r.request=t,r.response=a,r.isAxiosError=!0,r.toJSON=function(){return{message:this.message,name:this.name,description:this.description,number:this.number,fileName:this.fileName,lineNumber:this.lineNumber,columnNumber:this.columnNumber,stack:this.stack,config:this.config,code:this.code,status:this.response&&this.response.status?this.response.status:null}},r},$,de;function ke(){if(de)return $;de=1;var e=Ie;return $=function(n,i,t,a,u){var o=new Error(n);return e(o,i,t,a,u)},$}var _,he;function vr(){if(he)return _;he=1;var e=ke();return _=function(n,i,t){var a=t.config.validateStatus;!t.status||!a||a(t.status)?n(t):i(e("Request failed with status code "+t.status,t.config,null,t.request,t))},_}var I,pe;function br(){if(pe)return I;pe=1;var e=p;return I=e.isStandardBrowserEnv()?function(){return{write:function(i,t,a,u,o,l){var f=[];f.push(i+"="+encodeURIComponent(t)),e.isNumber(a)&&f.push("expires="+new Date(a).toGMTString()),e.isString(u)&&f.push("path="+u),e.isString(o)&&f.push("domain="+o),l===!0&&f.push("secure"),document.cookie=f.join("; ")},read:function(i){var t=document.cookie.match(new RegExp("(^|;\\s*)("+i+")=([^;]*)"));return t?decodeURIComponent(t[3]):null},remove:function(i){this.write(i,"",Date.now()-864e5)}}}():function(){return{write:function(){},read:function(){return null},remove:function(){}}}(),I}var k,me;function yr(){return me||(me=1,k=function(r){return/^([a-z][a-z\d+\-.]*:)?\/\//i.test(r)}),k}var F,ve;function Er(){return ve||(ve=1,F=function(r,n){return n?r.replace(/\/+$/,"")+"/"+n.replace(/^\/+/,""):r}),F}var H,be;function wr(){if(be)return H;be=1;var e=yr(),r=Er();return H=function(i,t){return i&&!e(t)?r(i,t):t},H}var M,ye;function Rr(){if(ye)return M;ye=1;var e=p,r=["age","authorization","content-length","content-type","etag","expires","from","host","if-modified-since","if-unmodified-since","last-modified","location","max-forwards","proxy-authorization","referer","retry-after","user-agent"];return M=function(i){var t={},a,u,o;return i&&e.forEach(i.split(`
`),function(f){if(o=f.indexOf(":"),a=e.trim(f.substr(0,o)).toLowerCase(),u=e.trim(f.substr(o+1)),a){if(t[a]&&r.indexOf(a)>=0)return;a==="set-cookie"?t[a]=(t[a]?t[a]:[]).concat([u]):t[a]=t[a]?t[a]+", "+u:u}}),t},M}var J,Ee;function Cr(){if(Ee)return J;Ee=1;var e=p;return J=e.isStandardBrowserEnv()?function(){var n=/(msie|trident)/i.test(navigator.userAgent),i=document.createElement("a"),t;function a(u){var o=u;return n&&(i.setAttribute("href",o),o=i.href),i.setAttribute("href",o),{href:i.href,protocol:i.protocol?i.protocol.replace(/:$/,""):"",host:i.host,search:i.search?i.search.replace(/^\?/,""):"",hash:i.hash?i.hash.replace(/^#/,""):"",hostname:i.hostname,port:i.port,pathname:i.pathname.charAt(0)==="/"?i.pathname:"/"+i.pathname}}return t=a(window.location.href),function(o){var l=e.isString(o)?a(o):o;return l.protocol===t.protocol&&l.host===t.host}}():function(){return function(){return!0}}(),J}var z,we;function L(){if(we)return z;we=1;function e(r){this.message=r}return e.prototype.toString=function(){return"Cancel"+(this.message?": "+this.message:"")},e.prototype.__CANCEL__=!0,z=e,z}var V,Re;function Ce(){if(Re)return V;Re=1;var e=p,r=vr(),n=br(),i=_e,t=wr(),a=Rr(),u=Cr(),o=ke(),l=B(),f=L();return V=function(s){return new Promise(function(h,E){var q=s.data,A=s.headers,N=s.responseType,C;function oe(){s.cancelToken&&s.cancelToken.unsubscribe(C),s.signal&&s.signal.removeEventListener("abort",C)}e.isFormData(q)&&delete A["Content-Type"];var d=new XMLHttpRequest;if(s.auth){var Xe=s.auth.username||"",We=s.auth.password?unescape(encodeURIComponent(s.auth.password)):"";A.Authorization="Basic "+btoa(Xe+":"+We)}var ue=t(s.baseURL,s.url);d.open(s.method.toUpperCase(),i(ue,s.params,s.paramsSerializer),!0),d.timeout=s.timeout;function fe(){if(d){var b="getAllResponseHeaders"in d?a(d.getAllResponseHeaders()):null,S=!N||N==="text"||N==="json"?d.responseText:d.response,R={data:S,status:d.status,statusText:d.statusText,headers:b,config:s,request:d};r(function(j){h(j),oe()},function(j){E(j),oe()},R),d=null}}if("onloadend"in d?d.onloadend=fe:d.onreadystatechange=function(){!d||d.readyState!==4||d.status===0&&!(d.responseURL&&d.responseURL.indexOf("file:")===0)||setTimeout(fe)},d.onabort=function(){d&&(E(o("Request aborted",s,"ECONNABORTED",d)),d=null)},d.onerror=function(){E(o("Network Error",s,null,d)),d=null},d.ontimeout=function(){var S=s.timeout?"timeout of "+s.timeout+"ms exceeded":"timeout exceeded",R=s.transitional||l.transitional;s.timeoutErrorMessage&&(S=s.timeoutErrorMessage),E(o(S,s,R.clarifyTimeoutError?"ETIMEDOUT":"ECONNABORTED",d)),d=null},e.isStandardBrowserEnv()){var le=(s.withCredentials||u(ue))&&s.xsrfCookieName?n.read(s.xsrfCookieName):void 0;le&&(A[s.xsrfHeaderName]=le)}"setRequestHeader"in d&&e.forEach(A,function(S,R){typeof q>"u"&&R.toLowerCase()==="content-type"?delete A[R]:d.setRequestHeader(R,S)}),e.isUndefined(s.withCredentials)||(d.withCredentials=!!s.withCredentials),N&&N!=="json"&&(d.responseType=s.responseType),typeof s.onDownloadProgress=="function"&&d.addEventListener("progress",s.onDownloadProgress),typeof s.onUploadProgress=="function"&&d.upload&&d.upload.addEventListener("progress",s.onUploadProgress),(s.cancelToken||s.signal)&&(C=function(b){d&&(E(!b||b&&b.type?new f("canceled"):b),d.abort(),d=null)},s.cancelToken&&s.cancelToken.subscribe(C),s.signal&&(s.signal.aborted?C():s.signal.addEventListener("abort",C))),q||(q=null),d.send(q)})},V}var X,Se;function B(){if(Se)return X;Se=1;var e=p,r=mr,n=Ie,i={"Content-Type":"application/x-www-form-urlencoded"};function t(l,f){!e.isUndefined(l)&&e.isUndefined(l["Content-Type"])&&(l["Content-Type"]=f)}function a(){var l;return(typeof XMLHttpRequest<"u"||typeof process<"u"&&Object.prototype.toString.call(process)==="[object process]")&&(l=Ce()),l}function u(l,f,c){if(e.isString(l))try{return(f||JSON.parse)(l),e.trim(l)}catch(s){if(s.name!=="SyntaxError")throw s}return(c||JSON.stringify)(l)}var o={transitional:{silentJSONParsing:!0,forcedJSONParsing:!0,clarifyTimeoutError:!1},adapter:a(),transformRequest:[function(f,c){return r(c,"Accept"),r(c,"Content-Type"),e.isFormData(f)||e.isArrayBuffer(f)||e.isBuffer(f)||e.isStream(f)||e.isFile(f)||e.isBlob(f)?f:e.isArrayBufferView(f)?f.buffer:e.isURLSearchParams(f)?(t(c,"application/x-www-form-urlencoded;charset=utf-8"),f.toString()):e.isObject(f)||c&&c["Content-Type"]==="application/json"?(t(c,"application/json"),u(f)):f}],transformResponse:[function(f){var c=this.transitional||o.transitional,s=c&&c.silentJSONParsing,v=c&&c.forcedJSONParsing,h=!s&&this.responseType==="json";if(h||v&&e.isString(f)&&f.length)try{return JSON.parse(f)}catch(E){if(h)throw E.name==="SyntaxError"?n(E,this,"E_JSON_PARSE"):E}return f}],timeout:0,xsrfCookieName:"XSRF-TOKEN",xsrfHeaderName:"X-XSRF-TOKEN",maxContentLength:-1,maxBodyLength:-1,validateStatus:function(f){return f>=200&&f<300},headers:{common:{Accept:"application/json, text/plain, */*"}}};return e.forEach(["delete","get","head"],function(f){o.headers[f]={}}),e.forEach(["post","put","patch"],function(f){o.headers[f]=e.merge(i)}),X=o,X}var Sr=p,xr=B(),Or=function(r,n,i){var t=this||xr;return Sr.forEach(i,function(u){r=u.call(t,r,n)}),r},W,xe;function Fe(){return xe||(xe=1,W=function(r){return!!(r&&r.__CANCEL__)}),W}var Oe=p,K=Or,qr=Fe(),Ar=B(),Nr=L();function G(e){if(e.cancelToken&&e.cancelToken.throwIfRequested(),e.signal&&e.signal.aborted)throw new Nr("canceled")}var Pr=function(r){G(r),r.headers=r.headers||{},r.data=K.call(r,r.data,r.headers,r.transformRequest),r.headers=Oe.merge(r.headers.common||{},r.headers[r.method]||{},r.headers),Oe.forEach(["delete","get","head","post","put","patch","common"],function(t){delete r.headers[t]});var n=r.adapter||Ar.adapter;return n(r).then(function(t){return G(r),t.data=K.call(r,t.data,t.headers,r.transformResponse),t},function(t){return qr(t)||(G(r),t&&t.response&&(t.response.data=K.call(r,t.response.data,t.response.headers,r.transformResponse))),Promise.reject(t)})},m=p,He=function(r,n){n=n||{};var i={};function t(c,s){return m.isPlainObject(c)&&m.isPlainObject(s)?m.merge(c,s):m.isPlainObject(s)?m.merge({},s):m.isArray(s)?s.slice():s}function a(c){if(m.isUndefined(n[c])){if(!m.isUndefined(r[c]))return t(void 0,r[c])}else return t(r[c],n[c])}function u(c){if(!m.isUndefined(n[c]))return t(void 0,n[c])}function o(c){if(m.isUndefined(n[c])){if(!m.isUndefined(r[c]))return t(void 0,r[c])}else return t(void 0,n[c])}function l(c){if(c in n)return t(r[c],n[c]);if(c in r)return t(void 0,r[c])}var f={url:u,method:u,data:u,baseURL:o,transformRequest:o,transformResponse:o,paramsSerializer:o,timeout:o,timeoutMessage:o,withCredentials:o,adapter:o,responseType:o,xsrfCookieName:o,xsrfHeaderName:o,onUploadProgress:o,onDownloadProgress:o,decompress:o,maxContentLength:o,maxBodyLength:o,transport:o,httpAgent:o,httpsAgent:o,cancelToken:o,socketPath:o,responseEncoding:o,validateStatus:l};return m.forEach(Object.keys(r).concat(Object.keys(n)),function(s){var v=f[s]||a,h=v(s);m.isUndefined(h)&&v!==l||(i[s]=h)}),i},Y,qe;function Me(){return qe||(qe=1,Y={version:"0.25.0"}),Y}var Tr=Me().version,se={};["object","boolean","number","function","string","symbol"].forEach(function(e,r){se[e]=function(i){return typeof i===e||"a"+(r<1?"n ":" ")+e}});var Ae={};se.transitional=function(r,n,i){function t(a,u){return"[Axios v"+Tr+"] Transitional option '"+a+"'"+u+(i?". "+i:"")}return function(a,u,o){if(r===!1)throw new Error(t(u," has been removed"+(n?" in "+n:"")));return n&&!Ae[u]&&(Ae[u]=!0,console.warn(t(u," has been deprecated since v"+n+" and will be removed in the near future"))),r?r(a,u,o):!0}};function gr(e,r,n){if(typeof e!="object")throw new TypeError("options must be an object");for(var i=Object.keys(e),t=i.length;t-- >0;){var a=i[t],u=r[a];if(u){var o=e[a],l=o===void 0||u(o,a,e);if(l!==!0)throw new TypeError("option "+a+" must be "+l);continue}if(n!==!0)throw Error("Unknown option "+a)}}var Ur={assertOptions:gr,validators:se},Je=p,Lr=_e,Ne=hr,Pe=Pr,D=He,ze=Ur,O=ze.validators;function P(e){this.defaults=e,this.interceptors={request:new Ne,response:new Ne}}P.prototype.request=function(r,n){if(typeof r=="string"?(n=n||{},n.url=r):n=r||{},!n.url)throw new Error("Provided config url is not valid");n=D(this.defaults,n),n.method?n.method=n.method.toLowerCase():this.defaults.method?n.method=this.defaults.method.toLowerCase():n.method="get";var i=n.transitional;i!==void 0&&ze.assertOptions(i,{silentJSONParsing:O.transitional(O.boolean),forcedJSONParsing:O.transitional(O.boolean),clarifyTimeoutError:O.transitional(O.boolean)},!1);var t=[],a=!0;this.interceptors.request.forEach(function(h){typeof h.runWhen=="function"&&h.runWhen(n)===!1||(a=a&&h.synchronous,t.unshift(h.fulfilled,h.rejected))});var u=[];this.interceptors.response.forEach(function(h){u.push(h.fulfilled,h.rejected)});var o;if(!a){var l=[Pe,void 0];for(Array.prototype.unshift.apply(l,t),l=l.concat(u),o=Promise.resolve(n);l.length;)o=o.then(l.shift(),l.shift());return o}for(var f=n;t.length;){var c=t.shift(),s=t.shift();try{f=c(f)}catch(v){s(v);break}}try{o=Pe(f)}catch(v){return Promise.reject(v)}for(;u.length;)o=o.then(u.shift(),u.shift());return o};P.prototype.getUri=function(r){if(!r.url)throw new Error("Provided config url is not valid");return r=D(this.defaults,r),Lr(r.url,r.params,r.paramsSerializer).replace(/^\?/,"")};Je.forEach(["delete","get","head","options"],function(r){P.prototype[r]=function(n,i){return this.request(D(i||{},{method:r,url:n,data:(i||{}).data}))}});Je.forEach(["post","put","patch"],function(r){P.prototype[r]=function(n,i,t){return this.request(D(t||{},{method:r,url:n,data:i}))}});var Br=P,Q,Te;function Dr(){if(Te)return Q;Te=1;var e=L();function r(n){if(typeof n!="function")throw new TypeError("executor must be a function.");var i;this.promise=new Promise(function(u){i=u});var t=this;this.promise.then(function(a){if(t._listeners){var u,o=t._listeners.length;for(u=0;u<o;u++)t._listeners[u](a);t._listeners=null}}),this.promise.then=function(a){var u,o=new Promise(function(l){t.subscribe(l),u=l}).then(a);return o.cancel=function(){t.unsubscribe(u)},o},n(function(u){t.reason||(t.reason=new e(u),i(t.reason))})}return r.prototype.throwIfRequested=function(){if(this.reason)throw this.reason},r.prototype.subscribe=function(i){if(this.reason){i(this.reason);return}this._listeners?this._listeners.push(i):this._listeners=[i]},r.prototype.unsubscribe=function(i){if(this._listeners){var t=this._listeners.indexOf(i);t!==-1&&this._listeners.splice(t,1)}},r.source=function(){var i,t=new r(function(u){i=u});return{token:t,cancel:i}},Q=r,Q}var Z,ge;function jr(){return ge||(ge=1,Z=function(r){return function(i){return r.apply(null,i)}}),Z}var ee,Ue;function $r(){if(Ue)return ee;Ue=1;var e=p;return ee=function(n){return e.isObject(n)&&n.isAxiosError===!0},ee}var Le=p,_r=Be,g=Br,Ir=He,kr=B();function Ve(e){var r=new g(e),n=_r(g.prototype.request,r);return Le.extend(n,g.prototype,r),Le.extend(n,r),n.create=function(t){return Ve(Ir(e,t))},n}var y=Ve(kr);y.Axios=g;y.Cancel=L();y.CancelToken=Dr();y.isCancel=Fe();y.VERSION=Me().version;y.all=function(r){return Promise.all(r)};y.spread=jr();y.isAxiosError=$r();ne.exports=y;ne.exports.default=y;var Fr=ne.exports,Hr=Fr;const Jr=Ge(Hr);export{Jr as A};
