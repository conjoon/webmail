/*!
 * Ext JS Library 3.0.2
 * Copyright(c) 2006-2009 Ext JS, LLC
 * licensing@extjs.com
 * http://www.extjs.com/license
 */
/**
 * @class Ext.SplitButton
 * @extends Ext.Button
 * A split button that provides a built-in dropdown arrow that can fire an event separately from the default
 * click event of the button.  Typically this would be used to display a dropdown menu that provides additional
 * options to the primary button action, but any custom handler can provide the arrowclick implementation.  Example usage:
 * <pre><code>
// display a dropdown menu:
new Ext.SplitButton({
	renderTo: 'button-ct', // the container id
   	text: 'Options',
   	handler: optionsHandler, // handle a click on the button itself
   	menu: new Ext.menu.Menu({
        items: [
        	// these items will render as dropdown menu items when the arrow is clicked:
	        {text: 'Item 1', handler: item1Handler},
	        {text: 'Item 2', handler: item2Handler}
        ]
   	})
});

// Instead of showing a menu, you provide any type of custom
// functionality you want when the dropdown arrow is clicked:
new Ext.SplitButton({
	renderTo: 'button-ct',
   	text: 'Options',
   	handler: optionsHandler,
   	arrowHandler: myCustomHandler
});
</code></pre>
 * @cfg {Function} arrowHandler A function called when the arrow button is clicked (can be used instead of click event)
 * @cfg {String} arrowTooltip The title attribute of the arrow
 * @constructor
 * Create a new menu button
 * @param {Object} config The config object
 * @xtype splitbutton
 */
Ext.SplitButton = Ext.extend(Ext.Button, {
	// private
    arrowSelector : 'em',
    split: true,

    // private
    initComponent : function(){
        Ext.SplitButton.superclass.initComponent.call(this);
        /**
         * @event arrowclick
         * Fires when this button's arrow is clicked
         * @param {MenuButton} this
         * @param {EventObject} e The click event
         */
        this.addEvents("arrowclick");
    },

    // private
    onRender : function(){
        Ext.SplitButton.superclass.onRender.apply(this, arguments);
        if(this.arrowTooltip){
            this.el.child(this.arrowSelector).dom[this.tooltipType] = this.arrowTooltip;
        }
    },

    /**
     * Sets this button's arrow click handler.
     * @param {Function} handler The function to call when the arrow is clicked
     * @param {Object} scope (optional) Scope for the function passed above
     */
    setArrowHandler : function(handler, scope){
        this.arrowHandler = handler;
        this.scope = scope;
    },

    getMenuClass : function(){
        return 'x-btn-split' + (this.arrowAlign == 'bottom' ? '-bottom' : '');
    },

    isClickOnArrow : function(e){
        return this.arrowAlign != 'bottom' ?
               e.getPageX() > this.el.child(this.buttonSelector).getRegion().right :
               e.getPageY() > this.el.child(this.buttonSelector).getRegion().bottom;
    },

    // private
    onClick : function(e, t){
        e.preventDefault();
        if(!this.disabled){
            if(this.isClickOnArrow(e)){
                if(this.menu && !this.menu.isVisible() && !this.ignoreNextClick){
                    this.showMenu();
                }
                this.fireEvent("arrowclick", this, e);
                if(this.arrowHandler){
                    this.arrowHandler.call(this.scope || this, this, e);
                }
            }else{
                if(this.enableToggle){
                    this.toggle();
                }
                this.fireEvent("click", this, e);
                if(this.handler){
                    this.handler.call(this.scope || this, this, e);
                }
            }
        }
    },

    // private
    isMenuTriggerOver : function(e){
        return this.menu && e.target.tagName == 'em';
    },

    // private
    isMenuTriggerOut : function(e, internal){
        return this.menu && e.target.tagName != 'em';
    }
});

Ext.reg('splitbutton', Ext.SplitButton);