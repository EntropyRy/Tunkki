"use strict";(self.webpackChunk=self.webpackChunk||[]).push([[574],{7574:(e,t,r)=>{r.r(t),r.d(t,{default:()=>y});r(2675),r(9463),r(2259),r(5700),r(6280),r(6918),r(3792),r(9572),r(4170),r(2892),r(9904),r(4185),r(875),r(287),r(6099),r(3362),r(825),r(7764),r(2953),r(6031);var n=r(2891),o=r(5093),i=r.n(o);function u(e){return u="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},u(e)}function c(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,p(n.key),n)}}function a(e,t,r){return t=f(t),function(e,t){if(t&&("object"==u(t)||"function"==typeof t))return t;if(void 0!==t)throw new TypeError("Derived constructors may only return object or undefined");return function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e)}(e,s()?Reflect.construct(t,r||[],f(e).constructor):t.apply(e,r))}function s(){try{var e=!Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){})))}catch(e){}return(s=function(){return!!e})()}function f(e){return f=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(e){return e.__proto__||Object.getPrototypeOf(e)},f(e)}function l(e,t){return l=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(e,t){return e.__proto__=t,e},l(e,t)}function h(e,t,r){return(t=p(t))in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}function p(e){var t=function(e,t){if("object"!=u(e)||!e)return e;var r=e[Symbol.toPrimitive];if(void 0!==r){var n=r.call(e,t||"default");if("object"!=u(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===t?String:Number)(e)}(e,"string");return"symbol"==u(t)?t:t+""}var y=function(e){function t(){return function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,t),a(this,t,arguments)}return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),Object.defineProperty(e,"prototype",{writable:!1}),t&&l(e,t)}(t,e),r=t,(n=[{key:"connect",value:function(){this.changePic(),this.hasRefreshIntervalValue&&this.startRefreshing()}},{key:"disconnect",value:function(){this.stopRefreshing()}},{key:"changePic",value:function(){var e=this;fetch(this.urlValue).then((function(e){return e.json()})).then((function(t){return e.setPic(t)}))}},{key:"startRefreshing",value:function(){var e=this;document.hidden?this.stopRefreshing():this.refreshTimer=setInterval((function(){e.changePic()}),this.refreshIntervalValue)}},{key:"stopRefreshing",value:function(){this.refreshTimer&&clearInterval(this.refreshTimer)}},{key:"setPic",value:function(e){if(e.taken){var t=i()(e.taken);this.badgeTarget.innerText=t.format("D.M.yyyy, HH:mm")}else this.badgeTarget.innerText="";e.url?(this.picTarget.classList.remove("shimmer"),this.picTarget.setAttribute("src",e.url)):(this.picTarget.classList.add("shimmer"),this.picTarget.setAttribute("src","/images/header-logo.svg"))}}])&&c(r.prototype,n),o&&c(r,o),Object.defineProperty(r,"prototype",{writable:!1}),r;var r,n,o}(n.Controller);h(y,"targets",["pic","badge"]),h(y,"values",{url:String,refreshInterval:Number})}}]);