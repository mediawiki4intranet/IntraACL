var exAttachCount = 0;
var addListener = function() {
  if (window.addEventListener) {
    return function(el, type, fn) { el.addEventListener(type, fn, false); };
  } else if (window.attachEvent) {
    return function(el, type, fn) {
      var f = function() { return fn.call(el, window.event); };
      el.attachEvent('on'+type, f);
    };
  } else {
    return function(el, type, fn) { element['on'+type] = fn; }
  }
}();
var exAttachHandler = function(ev, k, i, func)
{
  if (!ev) var ev = window.event;
  var t = ev.target;
  if (!t) t = ev.srcElement;
  if (t && t.nodeType == 3) t = t.parentNode; // Safari bug
  var nt = t;
  while (nt && (!nt[k] || nt[k] != i))
    nt = nt.parentNode;
  var st;
  if (st = func(ev, nt))
  {
    if (ev.stopPropagation)
      ev.stopPropagation();
    else
      ev.cancelBubble = true;
    if (st === 2)
    {
      if (ev.preventDefault)
        ev.preventDefault();
      ev.returnValue = false;
    }
  }
  return !st;
};
var exAttach = function(element, evname, func)
{
  var i = ++exAttachCount;
  var k = '_exAt'+evname;
  if (typeof(element) == 'string')
    element = document.getElementById(element);
  element[k] = i;
  addListener(element, evname, function(ev) { return exAttachHandler(ev, k, i, func); });
};
