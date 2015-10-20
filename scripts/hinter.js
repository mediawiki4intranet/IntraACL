/* Simple autocomplete for text inputs, with the support for multiple selection.

   Homepage: http://yourcmc.ru/wiki/SimpleAutocomplete
   License: MPL 2.0+ (http://www.mozilla.org/MPL/2.0/)
   Version: 2015-09-06
   (c) Vitaliy Filippov 2011-2015

   Usage:
     Include hinter.css, hinter.js on your page. Then write:
     var hint = new SimpleAutocomplete(input, dataLoader, params);

   Parameters:
     input
       The input, either id or DOM element reference (the input must have an id anyway).
     dataLoader(hint, value[, more])
       Callback which should load autocomplete options and then call:
       hint.replaceItems(newOptions, append)
         newOptions = [ [ name, value, disabled, checked OR id ] ], [ name, value ], ... ]
           name = HTML option name
           value = plaintext option value
           disabled = prevent selection of this option
           checked = (when multipleListener is set) is the item checked initially
           id = (when idField is set) the value ID for idField
         append = 'more' parameter should be passed here
       Callback parameters:
         hint
           This SimpleAutocomplete object
         value
           The string guess should be done based on
         more
           The 'page' of autocomplete options to load, 0 = first page.
           See also moreMarker option below.

   params attribute is an object with optional parameters:
     multipleDelimiter
       Pass a delimiter string (for example ',' or ';') to enable multiple selection.
       Item values cannot have leading or trailing whitespace. Input value will consist
       of selected item values separated by this delimiter plus single space.
       dataLoader should handle it's 'value' parameter accordingly in this case,
       because it will be just the raw value of the input, probably with incomplete
       item or items, typed by the user.
     multipleListener(hint, index, item)
       If you don't want to touch the input value, but want to use multi-select for
       your own purposes, specify a callback that will handle item clicks here.
       Also you can disable and check/uncheck items during loading in this mode.
     onChangeListener(hint, index, item)
       Callback which is called when input value is changed using this dropdown.
       index is the number of element which selection is changed, starting with 0.
       It must be used instead of normal 'onchange' event.
     emptyText
       Text to show when dataLoader returns no options. Empty (default) means 'hide hint'.
     prompt
       HTML text to be displayed before a non-empty option list. Empty by default.
     delay
       If this is set to a non-zero value, the autocompleter does no more than
       1 request in each delay milliseconds.
     moreMarker
       The server supplying hint options usually limits their count.
       But it's not always convenient having to type additional characters to
       narrow down the selection. Optionally you can supply additional item
       with special value equal to moreMarker value or '#MORE' at the end
       of the list, and SimpleAutocomplete will issue another request to
       dataLoader with incremented 'more' parameter when it will be clicked.
       You can also set moreMarker to false to disable this feature.
     idField
       If you specify an ID here, the selected value ID will be put into
       a hidden field with this ID, while the original hinted input will
       just contain the name of that value.
     persist
       If true, the hint layer will never be hidden. You can use it to create
       multiselect-like controls (see example at the homepage).
     className
       CSS class name for the hint layer. Default is 'hintLayer'.

   Destroy instance:
     hint.remove(); hint = null;
*/

// *** Constructor ***

var SimpleAutocomplete = function(input, dataLoader, params)
{
    if (typeof(input) == 'string')
        input = document.getElementById(input);
    if (!params)
        params = {};

    // Parameters
    this.options = params;
    this.input = input;
    this.dataLoader = dataLoader;
    this.multipleDelimiter = params.multipleDelimiter;
    this.multipleListener = params.multipleListener;
    this.onChangeListener = params.onChangeListener;
    this.emptyText = params.emptyText;
    this.prompt = params.prompt;
    this.delay = params.delay;
    this.moreMarker = params.moreMarker;
    this.idField = params.idField;
    this.persist = params.persist;
    this.className = params.className || 'hintLayer';
    if (this.idField && typeof(this.idField) == 'string')
        this.idField = document.getElementById(this.idField);

    // Default values
    if (this.moreMarker === undefined)
        this.moreMarker = '#MORE';
    if (this.delay === undefined)
        this.delay = 300;

    // Variables
    this.more = 0;
    this.timer = null;
    this.closure = [];
    this.items = [];
    this.skipHideCounter = 0;
    this.selectedIndex = -1;
    this.disabled = false;
    this.curValue = null;

    // *** Call initialise ***
    this.init();
};

// *** Instance methods ***

// Initialiser
SimpleAutocomplete.prototype.init = function()
{
    var e = this.input;
    var l = SimpleAutocomplete.SimpleAutocompletes;
    this.id = this.input.id + l.length;
    l.push(this);

    // Create hint layer
    var t = this.hintLayer = document.createElement('div');
    t.className = this.className;
    if (!this.persist)
    {
        t.style.display = 'none';
        t.style.position = 'absolute';
        t.style.zIndex = 1000;
        document.body.insertBefore(t, document.body.childNodes[0]);
    }
    else
    {
        e.nextSibling ? e.parentNode.insertBefore(t, e.nextSibling) : e.parentNode.appendChild(t);
    }

    // Remember instance
    e.SimpleAutocomplete_input = this;
    t.SimpleAutocomplete_layer = this;

    // Set autocomplete to off and reenable before unload
    if (typeof e.autocomplete !== 'undefined')
    {
        e.autocomplete = 'off';
        addListener(window, 'beforeunload', function() { e.autocomplete = 'on'; });
    }

    // Set event listeners
    var self = this;
    this.addRmListener('keydown', function(ev) { return self.onKeyDown(ev); });
    this.addRmListener('keyup', function(ev) { return self.onKeyUp(ev); });
    this.addRmListener('change', function() { return self.onChange(); });
    this.addRmListener('focus', function() { return self.onInputFocus(); });
    this.addRmListener('blur', function() { return self.onInputBlur(); });
    addListener(t, 'mousedown', function(ev) { return self.cancelBubbleOnHint(ev); });
    this.onChange(true);
};

// items = [ [ name, value ], [ name, value ], ... ]
SimpleAutocomplete.prototype.replaceItems = function(items, append)
{
    if (!append)
    {
        this.hintLayer.scrollTop = 0;
        this.selectedIndex = 0;
        this.items = [];
        if (!items || items.length == 0)
        {
            if (this.emptyText)
            {
                this.hintLayer.innerHTML = '<div class="hintEmptyText">'+this.emptyText+'</div>';
                this.enable();
            }
            else
                this.disable();
            return;
        }
        while (this.selectedIndex < items.length && items[this.selectedIndex][2])
            this.selectedIndex++;
        this.hintLayer.innerHTML = this.prompt ? '<div class="hintPrompt">'+this.prompt+'</div>' : '';
        this.enable();
    }
    if (this.multipleDelimiter)
    {
        var h = {};
        var old = this.input.value.split(this.multipleDelimiter);
        for (var i = 0; i < old.length; i++)
            h[old[i].trim()] = true;
        for (var i in items)
            items[i][3] = h[items[i][1]];
    }
    for (var i in items)
    {
        this.hintLayer.appendChild(this.makeItem(this.items.length, items[i]));
        this.items.push(items[i]);
    }
};

// Add removable listener on this.input (remember the function)
SimpleAutocomplete.prototype.addRmListener = function(n, f)
{
    this.closure[n] = f;
    addListener(this.input, n, f);
};

// Remove instance ("destructor")
SimpleAutocomplete.prototype.remove = function()
{
    if (!this.hintLayer)
        return;
    this.hintLayer.parentNode.removeChild(this.hintLayer);
    for (var i in this.closure)
    {
        removeListener(this.input, i, this.closure[i]);
    }
    for (var i = 0; i < SimpleAutocomplete.SimpleAutocompletes.length; i++)
    {
        if (SimpleAutocomplete.SimpleAutocompletes[i] == this)
        {
            SimpleAutocomplete.SimpleAutocompletes.splice(i, 1);
            break;
        }
    }
    this.closure = {};
    this.input = null;
    this.hintLayer = null;
    this.items = null;
};

// Create a drop-down list item, include checkbox if this.multipleDelimiter is true
SimpleAutocomplete.prototype.makeItem = function(index, item)
{
    var d = document.createElement('div');
    d.id = this.id+'_item_'+index;
    d.className = item[2] ? 'hintDisabledItem' : (this.selectedIndex == index ? 'hintActiveItem' : 'hintItem');
    d.title = item[1];
    if (this.multipleDelimiter || this.multipleListener)
    {
        var c = document.createElement('input');
        c.type = 'checkbox';
        c.id = this.id+'_check_'+index;
        c.checked = item[3] && true;
        c.disabled = item[2] && true;
        c.value = item[1];
        d.appendChild(c);
        var l = document.createElement('label');
        l.htmlFor = c.id;
        l.innerHTML = item[0];
        d.appendChild(l);
        addListener(l, 'click', this.preventCheck);
    }
    else
        d.innerHTML = item[0];
    var self = this;
    addListener(d, 'mouseover', function() { return self.onItemMouseOver(this); });
    addListener(d, 'click', function(ev) { return self.onItemClick(ev, this); });
    return d;
};

// Move highlight forward or back by 'by' items (integer)
SimpleAutocomplete.prototype.moveHighlight = function(by)
{
    var n = this.selectedIndex+by;
    if (n < 0)
        n = 0;
    while (this.items[n] && this.items[n][2])
        n += by;
    var elem = document.getElementById(this.id+'_item_'+n);
    if (!elem)
        return true;
    return this.highlightItem(elem);
};

// Make item 'elem' active (highlighted)
SimpleAutocomplete.prototype.highlightItem = function(elem)
{
    var ni = parseInt(elem.id.substr(this.id.length+6));
    if (this.items[ni][2])
        return false;
    if (this.selectedIndex >= 0)
    {
        var c = this.getItem();
        if (c)
        {
            c.className = this.items[this.selectedIndex][2] ? 'hintDisabledItem' : 'hintItem';
        }
    }
    this.selectedIndex = ni;
    elem.className = 'hintActiveItem';
    return false;
};

// Get index'th item, or current when index is null
SimpleAutocomplete.prototype.getItem = function(index)
{
    if (index == null)
        index = this.selectedIndex;
    if (index < 0)
        return null;
    return document.getElementById(this.id+'_item_'+this.selectedIndex);
};

// Select index'th item - change the input value and hide the hint if not a multi-select
SimpleAutocomplete.prototype.selectItem = function(index)
{
    if (this.items[index][2])
        return false;
    if (this.moreMarker && this.items[index][1] == this.moreMarker)
    {
        // User clicked 'more'. Load more items without delay.
        this.items.splice(index, 1);
        var elm = document.getElementById(this.id+'_item_'+index);
        elm.parentNode.removeChild(elm);
        this.more++;
        this.onChange(true);
        return;
    }
    if (!this.multipleDelimiter && !this.multipleListener)
    {
        this.input.value = this.items[index][1];
        if (this.idField)
            this.idField.value = this.items[index][3];
        this.hide();
    }
    else
    {
        document.getElementById(this.id+'_check_'+index).checked = this.items[index][3] = !this.items[index][3];
        if (this.multipleListener && !this.multipleListener(this, index, this.items[index]))
            return;
        this.toggleValue(index);
    }
    this.curValue = this.input.value;
    if (this.onChangeListener)
        this.onChangeListener(this, index, this.items[index]);
};

// Change input value so it will respect index'th item state in a multi-select
SimpleAutocomplete.prototype.toggleValue = function(index)
{
    var old = this.input.value.split(this.multipleDelimiter);
    for (var i = 0; i < old.length; i++)
        old[i] = old[i].trim();
    // Turn the clicked item on or off, preserving order
    if (!this.items[index][3])
    {
        for (var i = old.length-1; i >= 0; i--)
            if (old[i] == this.items[index][1])
                old.splice(i, 1);
        this.input.value = old.join(this.multipleDelimiter+' ');
    }
    else
    {
        var h = {};
        for (var i = 0; i < this.items.length; i++)
            if (this.items[i][3])
                h[this.items[i][1]] = true;
        var nl = [];
        for (var i = 0; i < old.length; i++)
        {
            if (h[old[i]])
            {
                delete h[old[i]];
                nl.push(old[i]);
            }
        }
        for (var i = 0; i < this.items.length; i++)
            if (this.items[i][3] && h[this.items[i][1]])
                nl.push(this.items[i][1]);
        this.input.value = nl.join(this.multipleDelimiter+' ');
    }
}

// Hide hinter
SimpleAutocomplete.prototype.hide = function()
{
    if (!this.persist)
    {
        if (!this.skipHideCounter)
        {
            this.hintLayer.style.display = 'none';
            return true;
        }
        else
            this.skipHideCounter = 0;
    }
};

// Show hinter
SimpleAutocomplete.prototype.show = function()
{
    if (!this.disabled && !this.persist && this.hintLayer.style.display == 'none')
    {
        var p = getOffset(this.input);
        this.hintLayer.style.top = (p.top+this.input.offsetHeight) + 'px';
        this.hintLayer.style.left = p.left + 'px';
        this.hintLayer.style.display = '';
        var sw = document.clientWidth || document.documentElement.clientWidth || document.body.clientWidth;
        if (p.left + this.hintLayer.offsetWidth > sw)
        {
            this.hintLayer.style.right = (sw-p.left-this.input.offsetWidth)+'px';
            this.hintLayer.style.left = '';
        }
        return true;
    }
};

// Disable hinter, for the case when there is no items and no empty text
SimpleAutocomplete.prototype.disable = function()
{
    this.disabled = true;
    this.hide();
};

// Enable hinter
SimpleAutocomplete.prototype.enable = function()
{
    this.disabled = false;
    if (this.hasFocus)
        this.show();
}

// *** Event handlers ***

// Prevent propagating label click to checkbox
SimpleAutocomplete.prototype.preventCheck = function(ev)
{
    return stopEvent(ev||window.event, false, true);
};

// Cancel event propagation
SimpleAutocomplete.prototype.cancelBubbleOnHint = function(ev)
{
    ev = ev||window.event;
    if (this.hasFocus)
        this.skipHideCounter++;
    return stopEvent(ev, true, false);
};

// Handle item mouse over
SimpleAutocomplete.prototype.onItemMouseOver = function(elm)
{
    return this.highlightItem(elm);
};

// Handle item clicks
SimpleAutocomplete.prototype.onItemClick = function(ev, elm)
{
    var index = parseInt(elm.id.substr(this.id.length+6));
    this.selectItem(index);
    return true;
};

// Handle user input, load new items
SimpleAutocomplete.prototype.onChange = function(force)
{
    var v = this.input.value.trim();
    if (!force)
        this.more = 0;
    if (v != this.curValue || force)
    {
        if (this.curValue !== null && this.idField)
            this.idField.value = '';
        this.curValue = v;
        if (!this.delay || force)
            this.dataLoader(this, v, this.more);
        else if (!this.timer)
        {
            var self = this;
            this.timer = setTimeout(function() {
                self.dataLoader(self, self.curValue, self.more);
                self.timer = null;
            }, this.delay);
        }
    }
    return true;
};

// Handle Enter key presses, cancel handling of arrow keys
SimpleAutocomplete.prototype.onKeyUp = function(ev)
{
    ev = ev||window.event;
    if (ev.keyCode == 38 || ev.keyCode == 40)
        this.show();
    if (ev.keyCode == 38 || ev.keyCode == 40 || ev.keyCode == 10 || ev.keyCode == 13)
    {
        if (this.hintLayer.style.display == '')
            return stopEvent(ev, true, true);
        else
            return true;
    }
    this.onChange();
    return true;
};

// Handle arrow keys and Enter
SimpleAutocomplete.prototype.onKeyDown = function(ev)
{
    if (this.hintLayer.style.display == 'none')
        return true;
    ev = ev||window.event;
    if (ev.keyCode == 38) // up
        this.moveHighlight(-1);
    else if (ev.keyCode == 40) // down
        this.moveHighlight(1);
    else if (ev.keyCode == 10 || ev.keyCode == 13) // enter
    {
        if (this.selectedIndex >= 0)
            this.selectItem(this.selectedIndex);
        return stopEvent(ev, true, true);
    }
    else if (ev.keyCode == 27) // escape
    {
        this.hide();
        return stopEvent(ev, true, true);
    }
    else
        return true;
    // scrolling
    if (this.selectedIndex >= 0)
    {
        var c = this.getItem();
        var t = this.hintLayer;
        var ct = getOffset(c).top + t.scrollTop - t.style.top.substr(0, t.style.top.length-2);
        var ch = c.scrollHeight;
        if (ct+ch-t.offsetHeight > t.scrollTop)
            t.scrollTop = ct+ch-t.offsetHeight;
        else if (ct < t.scrollTop)
            t.scrollTop = ct;
    }
    return stopEvent(ev, true, true);
};

// Called when input receives focus
SimpleAutocomplete.prototype.onInputFocus = function()
{
    this.show();
    this.hasFocus = true;
    return true;
};

// Called when input loses focus
SimpleAutocomplete.prototype.onInputBlur = function()
{
    if (!this.skipHideCounter && this.idField && !this.idField.value)
        this.input.value = '';
    this.hide();
    this.hasFocus = false;
    return true;
};

// *** Global variables ***

// List of all instances
SimpleAutocomplete.SimpleAutocompletes = [];

// Global mousedown handler, hides dropdowns when clicked outside
SimpleAutocomplete.GlobalMouseDown = function(ev)
{
    var target = ev.target || ev.srcElement;
    var esh;
    while (target)
    {
        esh = target.SimpleAutocomplete_input;
        if (esh)
            break;
        else if (target.SimpleAutocomplete_layer)
            return true;
        target = target.parentNode;
    }
    for (var i in SimpleAutocomplete.SimpleAutocompletes)
        if (SimpleAutocomplete.SimpleAutocompletes[i] != esh)
            SimpleAutocomplete.SimpleAutocompletes[i].hide();
    return true;
};

// *** UTILITY FUNCTIONS ***
// Remove this section if you already have these functions defined somewhere else

// Cross-browser add/remove event listeners
var addListener = function()
{
    return window.addEventListener
        ? function(el, type, fn) { el.addEventListener(type, fn, false); }
        : function(el, type, fn) { el.attachEvent('on'+type, fn); };
}();

var removeListener = function()
{
    return window.removeEventListener
        ? function(el, type, fn) { el.removeEventListener(type, fn, false); }
        : function(el, type, fn) { el.detachEvent('on'+type, fn); };
}();

// Cancel event bubbling and/or default action
var stopEvent = function(ev, cancelBubble, preventDefault)
{
    if (cancelBubble)
    {
        if (ev.stopPropagation)
            ev.stopPropagation();
        else
            ev.cancelBubble = true;
    }
    if (preventDefault && ev.preventDefault)
        ev.preventDefault();
    ev.returnValue = !preventDefault;
    return !preventDefault;
};

// Remove leading and trailing whitespace
if (!String.prototype.trim)
{
    String.prototype.trim = function()
    {
        return this.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
    };
}

// Get element position, relative to the top-left corner of page
var getOffset = function(elem)
{
    if (elem.getBoundingClientRect)
        return getOffsetRect(elem);
    else
        return getOffsetSum(elem);
};

// Get element position using getBoundingClientRect()
var getOffsetRect = function(elem)
{
    var box = elem.getBoundingClientRect();

    var body = document.body;
    var docElem = document.documentElement;

    var scrollTop = window.pageYOffset || docElem.scrollTop || body.scrollTop;
    var scrollLeft = window.pageXOffset || docElem.scrollLeft || body.scrollLeft;
    var clientTop = docElem.clientTop || body.clientTop || 0;
    var clientLeft = docElem.clientLeft || body.clientLeft || 0;
    var top = box.top + scrollTop - clientTop;
    var left = box.left + scrollLeft - clientLeft;

    return { top: Math.round(top), left: Math.round(left) };
};

// Get element position using sum of offsetTop/offsetLeft
var getOffsetSum = function(elem)
{
    var top = 0, left = 0;
    while(elem)
    {
        top = top + parseInt(elem.offsetTop);
        left = left + parseInt(elem.offsetLeft);
        elem = elem.offsetParent;
    }
    return { top: top, left: left };
};

// *** END UTILITY FUNCTIONS ***

// Set global mousedown listener
addListener(window, 'load', function() { addListener(document, 'mousedown', SimpleAutocomplete.GlobalMouseDown) });
