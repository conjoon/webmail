/*
 * Ext JS Library 3.0 Pre-alpha
 * Copyright(c) 2006-2008, Ext JS, LLC.
 * licensing@extjs.com
 * 
 * http://extjs.com/license
 */


Ext.SplitBar=function(dragElement,resizingElement,orientation,placement,existingProxy){this.el=Ext.get(dragElement,true);this.el.dom.unselectable="on";this.resizingEl=Ext.get(resizingElement,true);this.orientation=orientation||Ext.SplitBar.HORIZONTAL;this.minSize=0;this.maxSize=2000;this.animate=false;this.useShim=false;this.shim=null;if(!existingProxy){this.proxy=Ext.SplitBar.createProxy(this.orientation);}else{this.proxy=Ext.get(existingProxy).dom;}
this.dd=new Ext.dd.DDProxy(this.el.dom.id,"XSplitBars",{dragElId:this.proxy.id});this.dd.b4StartDrag=this.onStartProxyDrag.createDelegate(this);this.dd.endDrag=this.onEndProxyDrag.createDelegate(this);this.dragSpecs={};this.adapter=new Ext.SplitBar.BasicLayoutAdapter();this.adapter.init(this);if(this.orientation==Ext.SplitBar.HORIZONTAL){this.placement=placement||(this.el.getX()>this.resizingEl.getX()?Ext.SplitBar.LEFT:Ext.SplitBar.RIGHT);this.el.addClass("x-splitbar-h");}else{this.placement=placement||(this.el.getY()>this.resizingEl.getY()?Ext.SplitBar.TOP:Ext.SplitBar.BOTTOM);this.el.addClass("x-splitbar-v");}
this.addEvents("resize","moved","beforeresize","beforeapply");Ext.SplitBar.superclass.constructor.call(this);};Ext.extend(Ext.SplitBar,Ext.util.Observable,{onStartProxyDrag:function(x,y){this.fireEvent("beforeresize",this);this.overlay=Ext.DomHelper.append(document.body,{cls:"x-drag-overlay",html:"&#160;"},true);this.overlay.unselectable();this.overlay.setSize(Ext.lib.Dom.getViewWidth(true),Ext.lib.Dom.getViewHeight(true));this.overlay.show();Ext.get(this.proxy).setDisplayed("block");var size=this.adapter.getElementSize(this);this.activeMinSize=this.getMinimumSize();this.activeMaxSize=this.getMaximumSize();var c1=size-this.activeMinSize;var c2=Math.max(this.activeMaxSize-size,0);if(this.orientation==Ext.SplitBar.HORIZONTAL){this.dd.resetConstraints();this.dd.setXConstraint(this.placement==Ext.SplitBar.LEFT?c1:c2,this.placement==Ext.SplitBar.LEFT?c2:c1,this.tickSize);this.dd.setYConstraint(0,0);}else{this.dd.resetConstraints();this.dd.setXConstraint(0,0);this.dd.setYConstraint(this.placement==Ext.SplitBar.TOP?c1:c2,this.placement==Ext.SplitBar.TOP?c2:c1,this.tickSize);}
this.dragSpecs.startSize=size;this.dragSpecs.startPoint=[x,y];Ext.dd.DDProxy.prototype.b4StartDrag.call(this.dd,x,y);},onEndProxyDrag:function(e){Ext.get(this.proxy).setDisplayed(false);var endPoint=Ext.lib.Event.getXY(e);if(this.overlay){Ext.destroy(this.overlay);delete this.overlay;}
var newSize;if(this.orientation==Ext.SplitBar.HORIZONTAL){newSize=this.dragSpecs.startSize+
(this.placement==Ext.SplitBar.LEFT?endPoint[0]-this.dragSpecs.startPoint[0]:this.dragSpecs.startPoint[0]-endPoint[0]);}else{newSize=this.dragSpecs.startSize+
(this.placement==Ext.SplitBar.TOP?endPoint[1]-this.dragSpecs.startPoint[1]:this.dragSpecs.startPoint[1]-endPoint[1]);}
newSize=Math.min(Math.max(newSize,this.activeMinSize),this.activeMaxSize);if(newSize!=this.dragSpecs.startSize){if(this.fireEvent('beforeapply',this,newSize)!==false){this.adapter.setElementSize(this,newSize);this.fireEvent("moved",this,newSize);this.fireEvent("resize",this,newSize);}}},getAdapter:function(){return this.adapter;},setAdapter:function(adapter){this.adapter=adapter;this.adapter.init(this);},getMinimumSize:function(){return this.minSize;},setMinimumSize:function(minSize){this.minSize=minSize;},getMaximumSize:function(){return this.maxSize;},setMaximumSize:function(maxSize){this.maxSize=maxSize;},setCurrentSize:function(size){var oldAnimate=this.animate;this.animate=false;this.adapter.setElementSize(this,size);this.animate=oldAnimate;},destroy:function(removeEl){Ext.destroy(this.shim,Ext.get(this.proxy));this.dd.unreg();if(removeEl){this.el.remove();}
this.purgeListeners();}});Ext.SplitBar.createProxy=function(dir){var proxy=new Ext.Element(document.createElement("div"));proxy.unselectable();var cls='x-splitbar-proxy';proxy.addClass(cls+' '+(dir==Ext.SplitBar.HORIZONTAL?cls+'-h':cls+'-v'));document.body.appendChild(proxy.dom);return proxy.dom;};Ext.SplitBar.BasicLayoutAdapter=function(){};Ext.SplitBar.BasicLayoutAdapter.prototype={init:function(s){},getElementSize:function(s){if(s.orientation==Ext.SplitBar.HORIZONTAL){return s.resizingEl.getWidth();}else{return s.resizingEl.getHeight();}},setElementSize:function(s,newSize,onComplete){if(s.orientation==Ext.SplitBar.HORIZONTAL){if(!s.animate){s.resizingEl.setWidth(newSize);if(onComplete){onComplete(s,newSize);}}else{s.resizingEl.setWidth(newSize,true,.1,onComplete,'easeOut');}}else{if(!s.animate){s.resizingEl.setHeight(newSize);if(onComplete){onComplete(s,newSize);}}else{s.resizingEl.setHeight(newSize,true,.1,onComplete,'easeOut');}}}};Ext.SplitBar.AbsoluteLayoutAdapter=function(container){this.basic=new Ext.SplitBar.BasicLayoutAdapter();this.container=Ext.get(container);};Ext.SplitBar.AbsoluteLayoutAdapter.prototype={init:function(s){this.basic.init(s);},getElementSize:function(s){return this.basic.getElementSize(s);},setElementSize:function(s,newSize,onComplete){this.basic.setElementSize(s,newSize,this.moveSplitter.createDelegate(this,[s]));},moveSplitter:function(s){var yes=Ext.SplitBar;switch(s.placement){case yes.LEFT:s.el.setX(s.resizingEl.getRight());break;case yes.RIGHT:s.el.setStyle("right",(this.container.getWidth()-s.resizingEl.getLeft())+"px");break;case yes.TOP:s.el.setY(s.resizingEl.getBottom());break;case yes.BOTTOM:s.el.setY(s.resizingEl.getTop()-s.el.getHeight());break;}}};Ext.SplitBar.VERTICAL=1;Ext.SplitBar.HORIZONTAL=2;Ext.SplitBar.LEFT=1;Ext.SplitBar.RIGHT=2;Ext.SplitBar.TOP=3;Ext.SplitBar.BOTTOM=4;
