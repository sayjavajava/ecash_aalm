/*
 * Ext JS Library 1.1
 * Copyright(c) 2006-2007, Ext JS, LLC.
 * licensing@extjs.com
 * 
 * http://www.extjs.com/license
 */


Ext.CompositeElement=function(_1){this.elements=[];this.addElements(_1);};Ext.CompositeElement.prototype={isComposite:true,addElements:function(_2){if(!_2){return this;}if(typeof _2=="string"){_2=Ext.Element.selectorFunction(_2);}var _3=this.elements;var _4=_3.length-1;for(var i=0,_6=_2.length;i<_6;i++){_3[++_4]=Ext.get(_2[i]);}return this;},fill:function(_7){this.elements=[];this.add(_7);return this;},filter:function(_8){var _9=[];this.each(function(el){if(el.is(_8)){_9[_9.length]=el.dom;}});this.fill(_9);return this;},invoke:function(fn,_c){var _d=this.elements;for(var i=0,_f=_d.length;i<_f;i++){Ext.Element.prototype[fn].apply(_d[i],_c);}return this;},add:function(els){if(typeof els=="string"){this.addElements(Ext.Element.selectorFunction(els));}else{if(els.length!==undefined){this.addElements(els);}else{this.addElements([els]);}}return this;},each:function(fn,_12){var els=this.elements;for(var i=0,len=els.length;i<len;i++){if(fn.call(_12||els[i],els[i],this,i)===false){break;}}return this;},item:function(_16){return this.elements[_16]||null;},first:function(){return this.item(0);},last:function(){return this.item(this.elements.length-1);},getCount:function(){return this.elements.length;},contains:function(el){return this.indexOf(el)!==-1;},indexOf:function(el){return this.elements.indexOf(Ext.get(el));},removeElement:function(el,_1a){if(el instanceof Array){for(var i=0,len=el.length;i<len;i++){this.removeElement(el[i]);}return this;}var _1d=typeof el=="number"?el:this.indexOf(el);if(_1d!==-1){if(_1a){var d=this.elements[_1d];if(d.dom){d.remove();}else{d.parentNode.removeChild(d);}}this.elements.splice(_1d,1);}return this;},replaceElement:function(el,_20,_21){var _22=typeof el=="number"?el:this.indexOf(el);if(_22!==-1){if(_21){this.elements[_22].replaceWith(_20);}else{this.elements.splice(_22,1,Ext.get(_20));}}return this;},clear:function(){this.elements=[];}};(function(){Ext.CompositeElement.createCall=function(_23,_24){if(!_23[_24]){_23[_24]=function(){return this.invoke(_24,arguments);};}};for(var _25 in Ext.Element.prototype){if(typeof Ext.Element.prototype[_25]=="function"){Ext.CompositeElement.createCall(Ext.CompositeElement.prototype,_25);}}})();Ext.CompositeElementLite=function(els){Ext.CompositeElementLite.superclass.constructor.call(this,els);this.el=new Ext.Element.Flyweight();};Ext.extend(Ext.CompositeElementLite,Ext.CompositeElement,{addElements:function(els){if(els){if(els instanceof Array){this.elements=this.elements.concat(els);}else{var _28=this.elements;var _29=_28.length-1;for(var i=0,len=els.length;i<len;i++){_28[++_29]=els[i];}}}return this;},invoke:function(fn,_2d){var els=this.elements;var el=this.el;for(var i=0,len=els.length;i<len;i++){el.dom=els[i];Ext.Element.prototype[fn].apply(el,_2d);}return this;},item:function(_32){if(!this.elements[_32]){return null;}this.el.dom=this.elements[_32];return this.el;},addListener:function(_33,_34,_35,opt){var els=this.elements;for(var i=0,len=els.length;i<len;i++){Ext.EventManager.on(els[i],_33,_34,_35||els[i],opt);}return this;},each:function(fn,_3b){var els=this.elements;var el=this.el;for(var i=0,len=els.length;i<len;i++){el.dom=els[i];if(fn.call(_3b||el,el,this,i)===false){break;}}return this;},indexOf:function(el){return this.elements.indexOf(Ext.getDom(el));},replaceElement:function(el,_42,_43){var _44=typeof el=="number"?el:this.indexOf(el);if(_44!==-1){_42=Ext.getDom(_42);if(_43){var d=this.elements[_44];d.parentNode.insertBefore(_42,d);d.parentNode.removeChild(d);}this.elements.splice(_44,1,_42);}return this;}});Ext.CompositeElementLite.prototype.on=Ext.CompositeElementLite.prototype.addListener;if(Ext.DomQuery){Ext.Element.selectorFunction=Ext.DomQuery.select;}Ext.Element.select=function(_46,_47,_48){var els;if(typeof _46=="string"){els=Ext.Element.selectorFunction(_46,_48);}else{if(_46.length!==undefined){els=_46;}else{throw"Invalid selector";}}if(_47===true){return new Ext.CompositeElement(els);}else{return new Ext.CompositeElementLite(els);}};Ext.select=Ext.Element.select;