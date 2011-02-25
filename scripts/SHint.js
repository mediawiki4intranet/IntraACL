/* Подсказки в текстовых полях ввода. Использование:
     var hint = new SHint(input, style_prefix, fill_handler);
     SHint.init();
   Параметры конструктора:
     input - текстовое поле ввода, для которого делаем подсказку (ID или само)
     style_prefix - префикс стилей, используются
       P_tip (стиль окошка подсказки),
       P_tt (стиль простого текста в нём),
       P_ti (стиль элемента подсказки),
       P_ti_a (стиль выбранного элемента подсказки)
     fill_handler(SHint, value) - функция, по значению value дёргающая
       загрузку подсказок и передающая HTML-код в SHint.change_ajax()
       все элементы с классом P_ti в HTML-коде считаются подсказками
       значение берётся из их атрибута title=""
       Также эта же функция может присвоить предложение ко вводу, если
       value пустое. Присваивать в SHint.tip_div.innerHTML.
   Поля:
     element - поле ввода
     tip_div - <div> подсказки
     max_height - максимальная высота подсказки (в пикселах),
       дальше будет прокрутка. Задавать следует ДО вызова init().
   Функции:
     onset(ev, e) - callback для установки значения поля ввода в значение от
       элемента e (ev, e - см.ниже).
     change, keydown, keyup, keypress, change, e_focus, e_blur, e_mousedown -
       exAttach'ные обработчики соответствующих событий на поле ввода.
       exAttach'ность означает, что передаётся два параметра (ev, e),
       где ev - объект события, e - элемент, на котором оно РЕАЛЬНО произошло,
       значение возврата = 1 трактуется как "stop bubble", = 2 как
       "stop bubble" + "do not take default action"
   Требуется:
     exAttach.js
     offsetRect.js
   Страница: http://yourcmc.ru/wiki/SHint_JS
*/
var SHint = function(input, style_prefix, fill_handler)
{
    var sl = this;
    sl.style_prefix = style_prefix;
    if (typeof(input) == 'string')
        input = document.getElementById(input);
    sl.element = input;
    sl.fill_handler = fill_handler;
    sl.nodefocus = false;
    sl.focus = function(f)
    {
        sl.tip_div.style.display = f || sl.nodefocus ? '' : 'none';
        sl.nodefocus = false;
    };
    sl.change_highlight = function(ev, e)
    {
        var c;
        if (typeof(e) != 'object' && (!sl.current ||
            !(e = document.getElementById(sl.current.replace(/\d+/,function(m){return ''+(parseInt(m)+e)})))))
            return false;
        if (sl.current && (c = document.getElementById(sl.current)))
            c.className = c.className.replace(sl.style_prefix+'_ti_a', sl.style_prefix+'_ti');
        sl.current = e.id;
        e.className = e.className.replace(sl.style_prefix+'_ti', sl.style_prefix+'_ti_a');
        return false;
    };
    sl.keyup = function(ev, e)
    {
        if (ev.keyCode != 10 && ev.keyCode != 13)
            sl.focus(true);
        if (ev.keyCode == 38 || ev.keyCode == 40 || ev.keyCode == 10 || ev.keyCode == 13)
            return 2;
        sl.change(ev);
        return 0;
    };
    sl.keydown = function(ev, e)
    {
        return ev.keyCode == 10 || ev.keyCode == 13 ? 2 : 0;
    };
    sl.keypress = function(ev, e)
    {
        if (ev.keyCode == 38) // up
            sl.change_highlight(ev, -1);
        else if (ev.keyCode == 40) // down
            sl.change_highlight(ev, 1);
        else if (ev.keyCode == 10 || ev.keyCode == 13) // enter
        {
            var x;
            if (x = document.getElementById(sl.current))
                sl.set(null, x);
            return 2;
        }
        else
            return 0;
        // scrolling
        var c;
        if (sl.current && (c = document.getElementById(sl.current)))
        {
            var t = sl.tip_div;
            var ct = getOffset(c).top + t.scrollTop - t.style.top.substr(0, t.style.top.length-2);
            var ch = c.scrollHeight;
            if (ct+ch-t.offsetHeight > t.scrollTop)
                t.scrollTop = ct+ch-t.offsetHeight;
            else if (ct < t.scrollTop)
                t.scrollTop = ct;
        }
        return 2;
    };
    sl.change_ajax = function(text)
    {
        sl.current = '';
        sl.tip_div.innerHTML = text;
        sl.tip_div.scrollTop = 0;
        if (sl.scriptMaxHeight)
            sl.tip_div.style.height = (sl.tip_div.scrollHeight > sl.max_height
                ? sl.max_height : sl.tip_div.scrollHeight) + 'px';
        sl.find_attach(sl.tip_div);
    };
    sl.find_attach = function(e)
    {
        for (var i in e.childNodes)
        {
            if (e.childNodes[i] &&
                e.childNodes[i].className &&
                e.childNodes[i].className.indexOf(sl.style_prefix+'_ti') >= 0)
            {
                exAttach(e.childNodes[i], 'mouseover', sl.change_highlight);
                exAttach(e.childNodes[i], 'click', sl.set);
                if (!sl.current)
                    sl.change_highlight(null, e.childNodes[i]);
            }
            else
                sl.find_attach(e.childNodes[i]);
        }
    };
    sl.change = function(ev)
    {
        var v = sl.element.value.trim();
        if (v != sl.curValue)
        {
            sl.curValue = v;
            sl.fill_handler(sl, v);
        }
    };
    sl.set = function(ev, e)
    {
        sl.element.value = e.title;
        sl.focus(false);
        if (sl.onset)
            sl.onset(ev, e);
    };
    sl.h_focus = function() { sl.focus(true); return 1; };
    sl.h_blur = function() { sl.focus(false); return 1; };
    sl.t_mousedown = function() { sl.nodefocus++; };
    sl.d_mousedown = function()
    {
        sl.focus(false);
    };
    sl.e_mousedown = function() { sl.nodefocus++; };
    sl.init = function()
    {
        var e = sl.element;
        var p = getOffset(e);
        var t = sl.tip_div = document.createElement('div');
        t.className = sl.style_prefix + '_tip';
        t.style.display = 'none';
        t.style.position = 'absolute';
        t.style.top = (p.top+e.offsetHeight) + 'px';
        t.style.zIndex = 1000;
        t.style.left = p.left + 'px';
        if (sl.max_height)
        {
            t.style.overflowY = 'scroll';
            try { t.style.overflow = '-moz-scrollbars-vertical'; } catch(exc) {}
            t.style.maxHeight = sl.max_height+'px';
            if (!t.style.maxHeight)
                sl.scriptMaxHeight = true;
        }
        document.body.appendChild(t);
        sl.element._e_SHint = sl;
        sl.tip_div._t_SHint = sl;
        SHint.SHints.push(sl);
        var msie = navigator.userAgent.match('MSIE') && !navigator.userAgent.match('Opera');
        if (msie)
            exAttach(e, 'keydown', sl.keypress);
        else
        {
            exAttach(e, 'keydown', sl.keydown);
            exAttach(e, 'keypress', sl.keypress);
        }
        exAttach(e, 'keyup', sl.keyup);
        exAttach(e, 'change', sl.change);
        exAttach(e, 'focus', sl.h_focus);
        exAttach(e, 'blur', sl.h_blur);
        sl.change(null);
    };
};

SHint.SHints = [];

SHint.GlobalMouseDown = function(ev, e)
{
    var target = ev.target || ev.srcElement;
    var esh;
    while (target)
    {
        if (esh = target._e_SHint)
            break;
        else if (target._t_SHint)
        {
            target._t_SHint.nodefocus = true;
            return;
        }
        target = target.parentNode;
    }
    for (var i in SHint.SHints)
        if (SHint.SHints[i] != esh)
            SHint.SHints[i].focus(false);
};

exAttach(window, 'load', function() { exAttach(document, 'mousedown', SHint.GlobalMouseDown) });
