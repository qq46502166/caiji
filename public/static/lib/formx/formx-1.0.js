/**@auth sole@url https://gitee.com/sole/formx@time 2015年8月3日@verson 1.0*/
(function(a){a.fn.getForm=function(f){var h=a(this),b=["input","select","textarea"],e=h.find(b.join(",")),d={},g="";function c(j){var i="";for(g in j){i+="&"+g+"="+j[g]}return i.substring(1)}e.each(function(j,k){if(a(k).attr("name")){d[a(k).attr("name")]=a(k).val()}});if(f=="str"){d=c(d)}return d}})(jQuery);(function(c){var a=function(d){return true};var b={errClass:"err",okClass:"success",errElem:"",blured:a,eleClick:a,docClick:a,regexped:a,move:a,keyup:a,urled:a,saved:a,debug:false,saveBefore:a};c.fn.formx=function(e){var d=c.fn.extend({},b,e);return this.each(function(){var p=c(this),k=p.find("input[required], textarea[required]"),l=p.find("input, textarea"),n=p.find('input[type="submit"]'),i={};l.each(function(q,s){var r=c.trim(c(s).attr("pattern"));if(r){i[c(s).attr("name")]=r;c(s).removeAttr("pattern")}});function m(){var q=[];var r=p.find("."+d.errClass).length;k=p.find("input[required], textarea[required]");data=p.getForm();k.each(function(s,t){if(c.trim(data[c(t).attr("name")])){q.push(c(t).attr("name"))}});(k.length==q.length&&!r)?n.removeAttr("disabled"):n.attr("disabled","disabled")}function o(s){var r=c.trim(s.val()),q=s.parents(d.errElem);q[(!r?"add":"remove")+"Class"](d.errClass);q[(!r?"remove":"add")+"Class"](d.okClass);return !r?false:true}function g(v){var u=c.trim(v.val()),s=v.parents(d.errElem),q=v.attr("name"),t=i[q];var r=new RegExp(t);s[(!r.test(u)?"add":"remove")+"Class"](d.errClass);s[(!r.test(u)?"remove":"add")+"Class"](d.okClass);if(r.test(u)){d.regexped(v)}}function f(v){var u=c.trim(v.val()),t=v.parents(d.errElem),r=v.data("url"),q=v.attr("name");var s={};s[q]=u;c.ajax({async:false,type:"post",dataType:"json",data:s,url:r,success:function(w){t[(!w.code?"add":"remove")+"Class"](d.errClass);t[(!reg.code?"remove":"add")+"Class"](d.okClass);if(w.code==1){t.removeClass(d.errClass).addClass(d.okClass)}else{t.addClass(d.errClass).removeClass(d.okClass)}d.urled(v,w)}})}function h(u){var t=u.data("group");var s=p.find('input[data-group="'+t+'"]');var r=s.parents(d.errElem);var q=[];s.each(function(v,w){if(!c.trim(c(w).val())){q.push(v)}});if(!q.length){if(s.eq(0).val()===s.eq(1).val()){r.removeClass(d.errClass).addClass(d.okClass)}else{r.addClass(d.errClass).removeClass(d.okClass)}}}function j(r,q){_parent=r.parents(d.errElem),_url=r.data("url"),_group=r.data("group"),_name=r.attr("name"),_re=i[_name];_parent=d.errElem?r.parents(d.errElem):_parent;if(o(r)){_re&&g(r);_group&&h(r);if(q=="blur"){_url&&f(r)}}}k.live("blur",function(){var q=c(this);j(q,"blur");m()}).live("keyup",function(){var q=c(this);j(q,"keyup");m()});l.live("click",function(q){var r=c(this);d.eleClick(r);q.stopPropagation()}).live("keyup",function(){var q=c(this);d.keyup(q)}).live("blur",function(){var q=c(this);d.blured(q)});c(document).on("click",function(q){d.docClick(c(q.target))});p.on("mousemove",function(){m();d.move(data)}).on("submit",function(){var q=p.attr("action"),s=p.attr("method"),r=p.getForm();d.saveBefore&&d.saveBefore(r);if(d.debug){window.console&&console.log(q,s,r)}if(!n.attr("disabled")){c.ajax({async:false,type:s,dataType:"json",data:r,url:q,success:function(t){d.saved(t)}})}return false})})}})(jQuery);