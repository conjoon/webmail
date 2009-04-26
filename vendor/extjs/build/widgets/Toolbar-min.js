/*
 * Ext JS Library 3.0 Pre-alpha
 * Copyright(c) 2006-2008, Ext JS, LLC.
 * licensing@extjs.com
 * 
 * http://extjs.com/license
 */


Ext.layout.ToolbarLayout=Ext.extend(Ext.layout.ContainerLayout,{monitorResize:true,triggerWidth:18,lastOverflow:false,noItemsMenuText:'<div class="x-toolbar-no-items">(None)</div>',onLayout:function(ct,target){if(!this.leftTr){target.addClass('x-toolbar-layout-ct');target.insertHtml('beforeEnd','<table cellspacing="0" class="x-toolbar-ct"><tbody><tr><td class="x-toolbar-left" align="left"><table cellspacing="0"><tbody><tr class="x-toolbar-left-row"></tr></tbody></table></td><td class="x-toolbar-right" align="right"><table cellspacing="0" class="x-toolbar-right-ct"><tbody><tr><td><table cellspacing="0"><tbody><tr class="x-toolbar-right-row"></tr></tbody></table></td><td><table cellspacing="0"><tbody><tr class="x-toolbar-extras-row"></tr></tbody></table></td></tr></tbody></td></tr></tbody></table>');this.leftTr=target.child('tr.x-toolbar-left-row',true);this.rightTr=target.child('tr.x-toolbar-right-row',true);this.extrasTr=target.child('tr.x-toolbar-extras-row',true);}
var side=this.leftTr;var pos=0;var items=ct.items.items;for(var i=0,len=items.length,c;i<len;i++,pos++){c=items[i];if(c.isFill){side=this.rightTr;pos=-1;}else if(!c.rendered){c.render(this.insertCell(c,side,pos));}else{if(!c.xtbHidden&&!this.isValidParent(c,side.childNodes[pos])){var td=this.insertCell(c,side,pos);td.appendChild(c.getDomPositionEl().dom);c.container=Ext.get(td);}}}
this.cleanup(this.leftTr);this.cleanup(this.rightTr);this.cleanup(this.extrasTr);this.fitToSize(target);},cleanup:function(row){var cn=row.childNodes;for(var i=cn.length-1,c;i>=0&&(c=cn[i]);i--){if(!c.firstChild){row.removeChild(c);}}},insertCell:function(c,side,pos){var td=document.createElement('td');td.className='x-toolbar-cell';side.insertBefore(td,side.childNodes[pos]||null);return td;},hideItem:function(item){var h=(this.hiddens=this.hiddens||[]);h.push(item);item.xtbHidden=true;item.xtbWidth=item.getDomPositionEl().dom.parentNode.offsetWidth;item.hide();},unhideItem:function(item){item.show();item.xtbHidden=false;this.hiddens.remove(item);if(this.hiddens.length<1){delete this.hiddens;}},getItemWidth:function(c){return c.hidden?(c.xtbWidth||0):c.getDomPositionEl().dom.parentNode.offsetWidth;},fitToSize:function(t){if(this.container.enableOverflow===false){return;}
var w=t.dom.clientWidth;var lw=this.lastWidth||0;this.lastWidth=w;var iw=t.dom.firstChild.offsetWidth;var clipWidth=w-this.triggerWidth;var hideIndex=-1;if(iw>w||(this.hiddens&&w>lw)){var i,items=this.container.items.items,len=items.length,c;var loopWidth=0;for(i=0;i<len;i++){c=items[i];if(!c.isFill){loopWidth+=this.getItemWidth(c);if(loopWidth>clipWidth){if(!c.xtbHidden){this.hideItem(c);}}else{if(c.xtbHidden){this.unhideItem(c);}}}}}
if(this.hiddens){this.initMore();if(!this.lastOverflow){this.container.fireEvent('overflowchange',this.container,true);this.lastOverflow=true;}}else if(this.more){this.clearMenu();this.more.destroy();delete this.more;if(this.lastOverflow){this.container.fireEvent('overflowchange',this.container,false);this.lastOverflow=false;}}},createMenuConfig:function(c,hideOnClick){var cfg={text:c.text,iconCls:c.iconCls,icon:c.icon,itemId:c.itemId,disabled:c.disabled,handler:c.handler,scope:c.scope,menu:c.menu};cfg.hideOnClick=hideOnClick;delete cfg.xtype;delete cfg.id;return cfg;},addComponentToMenu:function(m,c){if(c instanceof Ext.Toolbar.Separator){m.add('-');}else if(typeof c.isXType=='function'){if(c.isXType('splitbutton')){m.add(this.createMenuConfig(c,true));}else if(c.isXType('button')){m.add(this.createMenuConfig(c,!c.menu));}else if(c.isXType('buttongroup')){m.add('-');c.items.each(function(item){this.addComponentToMenu(m,item);},this);m.add('-');}}},clearMenu:function(){var m=this.moreMenu;if(m&&m.items){this.moreMenu.items.each(function(item){delete item.menu;});}},beforeMoreShow:function(m){this.clearMenu();m.removeAll();for(var i=0,h=this.container.items.items,len=h.length,c;i<len;i++){c=h[i];if(c.xtbHidden){this.addComponentToMenu(m,c);}}
if(m.items.length<1){m.add(this.noItemsMenuText);}},initMore:function(){if(!this.more){this.moreMenu=new Ext.menu.Menu({listeners:{beforeshow:this.beforeMoreShow,scope:this}});this.more=new Ext.Button({iconCls:'x-toolbar-more-icon',cls:'x-toolbar-more',menu:this.moreMenu});var td=this.insertCell(this.more,this.extrasTr,100);this.more.render(td);}}});Ext.Container.LAYOUTS['toolbar']=Ext.layout.ToolbarLayout;Ext.Toolbar=function(config){if(Ext.isArray(config)){config={items:config,layout:'toolbar'};}else{config=Ext.apply({layout:'toolbar'},config);if(config.buttons){config.items=config.buttons;}}
Ext.Toolbar.superclass.constructor.call(this,config);};(function(){var T=Ext.Toolbar;Ext.extend(T,Ext.Container,{defaultType:'button',trackMenus:true,internalDefaults:{removeMode:'container',hideParent:true},toolbarCls:'x-toolbar',initComponent:function(){T.superclass.initComponent.call(this);this.addEvents('overflowchange');},onRender:function(ct,position){if(!this.el){if(!this.autoCreate){this.autoCreate={cls:this.toolbarCls+' x-small-editor'}}
this.el=ct.createChild(Ext.apply({id:this.id},this.autoCreate),position);}},add:function(){var a=arguments,l=a.length;for(var i=0;i<l;i++){var el=a[i];if(el.isFormField){this.addField(el);}else if(el.render){this.addItem(el);}else if(typeof el=="string"){if(el=="separator"||el=="-"){this.addSeparator();}else if(el==" "){this.addSpacer();}else if(el=="->"){this.addFill();}else{this.addText(el);}}else if(el.tag){this.addDom(el);}else if(el.tagName){this.addElement(el);}else if(typeof el=="object"){if(el.xtype){this.addItem(Ext.create(el,'button'));}else{this.addButton(el);}}}},addSeparator:function(){return this.addItem(new T.Separator());},addSpacer:function(){return this.addItem(new T.Spacer());},addFill:function(){this.addItem(new T.Fill());},addElement:function(el){var item=new T.Item({el:el});this.addItem(item);return item;},addItem:function(item){Ext.Toolbar.superclass.add.apply(this,arguments);return item;},addButton:function(config){if(Ext.isArray(config)){var buttons=[];for(var i=0,len=config.length;i<len;i++){buttons.push(this.addButton(config[i]));}
return buttons;}
var b=config;if(!b.events){b=config.split?new T.SplitButton(config):new T.Button(config);}
this.initMenuTracking(b);this.addItem(b);return b;},initMenuTracking:function(item){if(this.trackMenus&&item.menu){this.mon(item,{'menutriggerover':this.onButtonTriggerOver,'menushow':this.onButtonMenuShow,'menuhide':this.onButtonMenuHide,scope:this});}},addText:function(text){var t=new T.TextItem(text);this.addItem(t);return t;},insertButton:function(index,item){if(Ext.isArray(item)){var buttons=[];for(var i=0,len=item.length;i<len;i++){buttons.push(this.insertButton(index+i,item[i]));}
return buttons;}
if(!(item instanceof T.Button)){item=new T.Button(item);}
Ext.Toolbar.superclass.insert.call(this,index,item);return item;},addDom:function(config){var item=new T.Item({autoEl:config});this.addItem(item);return item;},addField:function(field){this.addItem(field);return field;},applyDefaults:function(c){c=Ext.Toolbar.superclass.applyDefaults.call(this,c);var d=this.internalDefaults;if(c.events){Ext.applyIf(c.initialConfig,d);Ext.apply(c,d);}else{Ext.applyIf(c,d);}
return c;},onDisable:function(){this.items.each(function(item){if(item.disable){item.disable();}});},onEnable:function(){this.items.each(function(item){if(item.enable){item.enable();}});},onButtonTriggerOver:function(btn){if(this.activeMenuBtn&&this.activeMenuBtn!=btn){this.activeMenuBtn.hideMenu();btn.showMenu();this.activeMenuBtn=btn;}},onButtonMenuShow:function(btn){this.activeMenuBtn=btn;},onButtonMenuHide:function(btn){delete this.activeMenuBtn;}});Ext.reg('toolbar',Ext.Toolbar);T.Item=Ext.extend(Ext.BoxComponent,{hideParent:true,enable:Ext.emptyFn,disable:Ext.emptyFn,focus:Ext.emptyFn});Ext.reg('tbitem',T.Item);T.Separator=Ext.extend(T.Item,{onRender:function(ct,position){this.el=ct.createChild({tag:'span',cls:'xtb-sep'},position);}});Ext.reg('tbseparator',T.Separator);T.Spacer=Ext.extend(T.Item,{onRender:function(ct,position){this.el=ct.createChild({tag:'div',cls:'xtb-spacer',style:this.width?'width:'+this.width+'px':''},position);}});Ext.reg('tbspacer',T.Spacer);T.Fill=Ext.extend(T.Item,{render:Ext.emptyFn,isFill:true});Ext.reg('tbfill',T.Fill);T.TextItem=Ext.extend(T.Item,{constructor:function(config){if(typeof config=='string'){config={autoEl:{cls:'xtb-text',html:config}};}else{config.autoEl={cls:'xtb-text',html:config.text||''};}
T.TextItem.superclass.constructor.call(this,config);},setText:function(t){if(this.rendered){this.el.dom.innerHTML=t;}else{this.autoEl.html=t;}}});Ext.reg('tbtext',T.TextItem);T.Button=Ext.extend(Ext.Button,{});T.SplitButton=Ext.extend(Ext.SplitButton,{});Ext.reg('tbbutton',T.Button);Ext.reg('tbsplit',T.SplitButton);})();Ext.ButtonGroup=Ext.extend(Ext.Panel,{baseCls:'x-btn-group',layout:'table',defaultType:'button',frame:true,internalDefaults:{removeMode:'container',hideParent:true},initComponent:function(){this.layoutConfig=this.layoutConfig||{};Ext.applyIf(this.layoutConfig,{columns:this.columns});if(!this.title){this.addClass('x-btn-group-notitle');}
this.on('afterlayout',this.onAfterLayout,this);Ext.ButtonGroup.superclass.initComponent.call(this);},applyDefaults:function(c){c=Ext.ButtonGroup.superclass.applyDefaults.call(this,c);var d=this.internalDefaults;if(c.events){Ext.applyIf(c.initialConfig,d);Ext.apply(c,d);}else{Ext.applyIf(c,d);}
return c;},onAfterLayout:function(){var bodyWidth=this.body.getFrameWidth('lr')+this.body.dom.firstChild.offsetWidth;this.body.setWidth(bodyWidth);this.el.setWidth(bodyWidth+this.getFrameWidth());}});Ext.reg('buttongroup',Ext.ButtonGroup);