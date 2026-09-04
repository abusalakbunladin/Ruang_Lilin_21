(function () {
'use strict';
var BREAKPOINT = 1180;
var MIN_SCALE = 0.72;
var SAFETY_MARGIN = 4;
var stage = null;
var layout = null;
var framePending = false;
var resizeObserver = null;
function grabElements() {
stage = document.getElementById('game-fit-stage');
layout = document.getElementById('game-layout');
return !!(stage && layout);
}
function resetToNatural() {
layout.style.transform = 'none';
layout.style.top = '0px';
layout.style.left = '0px';
}
function fit() {
framePending = false;
if (!stage || !layout) {
if (!grabElements()) return;
}
if (window.innerWidth < BREAKPOINT) {
resetToNatural();
return;
}
var naturalW = layout.offsetWidth;
var naturalH = layout.offsetHeight;
var availW = stage.clientWidth;
var availH = stage.clientHeight;
if (!naturalW || !naturalH || !availW || !availH) return;
var rawScale = Math.min(
1,
(availW - SAFETY_MARGIN) / naturalW,
(availH - SAFETY_MARGIN) / naturalH
);
var scale = Math.max(MIN_SCALE, rawScale);
var visualH = naturalH * scale;
stage.style.overflowY = (scale <= MIN_SCALE && visualH > availH) ? 'auto' : 'hidden';
if (scale >= 1) {
resetToNatural();
return;
}
var visualW = naturalW * scale;
var left = Math.max(0, (availW - visualW) / 2);
var top = Math.max(0, (availH - visualH) / 2);
layout.style.transform = 'scale(' + scale.toFixed(4) + ')';
layout.style.left = left + 'px';
layout.style.top = top + 'px';
}
function scheduleFit() {
if (framePending) return;
framePending = true;
requestAnimationFrame(fit);
}
window.addEventListener('resize', scheduleFit);
window.addEventListener('orientationchange', scheduleFit);
function init() {
if (!grabElements()) return;
scheduleFit();
if ('ResizeObserver' in window) {
resizeObserver = new ResizeObserver(scheduleFit);
resizeObserver.observe(layout);
}
}
if (document.readyState === 'loading') {
document.addEventListener('DOMContentLoaded', init);
} else {
init();
}
})();
