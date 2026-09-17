{{-- Einbettungs-Script des Bewertungs-Widgets (#12), ausgeliefert von ReviewWidgetController::script(). --}}
(function () {
  var script = document.currentScript;
  if (!script || !script.parentNode) {
    return;
  }
  var src = @js($widgetUrl);
  var frame = document.createElement('iframe');
  frame.src = src;
  frame.title = @js($title);
  frame.loading = 'lazy';
  frame.setAttribute('scrolling', 'no');
  frame.style.cssText = 'display:block;width:100%;max-width:420px;height:360px;border:0;overflow:hidden;';
  script.parentNode.insertBefore(frame, script.nextSibling);

  var origin = new URL(src).origin;
  window.addEventListener('message', function (event) {
    if (event.origin !== origin || event.source !== frame.contentWindow) {
      return;
    }
    var data = event.data || {};
    if (data.type === 'sun-review-widget:height' && typeof data.height === 'number') {
      frame.style.height = Math.ceil(data.height) + 'px';
    }
  });
})();
