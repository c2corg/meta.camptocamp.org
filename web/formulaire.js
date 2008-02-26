function xGetElementsByClassName(clsName, parentEle, tagName, fn)
{
  var found = new Array();
  var re = new RegExp('\\b'+clsName+'\\b', 'i');
  var list = xGetElementsByTagName(tagName, parentEle);
  for (var i = 0; i < list.length; ++i) {
    if (list[i].className.search(re) != -1) {
      found[found.length] = list[i];
      if (fn) fn(list[i]);
    }
  }
  return found;
}
function xGetElementsByTagName(tagName, parentEle)
{
  var list = null;
  tagName = tagName || '*';
  parentEle = parentEle || document;
  if (parentEle.getElementsByTagName) {
    list = parentEle.getElementsByTagName(tagName);
  };
  return list || new Array();
}
window.onload = function()
{
  my_date = xGetElementsByClassName('sel_date', null, null);
  set_date();
  my_param = xGetElementsByClassName('param', null, null);
}

function set_date() {
  if ((my_date[0].value != "00") && (my_date[1].value != "00") && (my_date[2].value != "0000")) {
    my_date[6].value = my_date[2].value + "-" + my_date[1].value + "-" + my_date[0].value ;
    if ((my_date[3].value != "00") && (my_date[4].value != "00") && (my_date[5].value != "0000")) {
    my_date[6].value += "," + my_date[5].value + "-" + my_date[4].value + "-" + my_date[3].value ;
    }
  } else {
    my_date[6].value = "" ;
  }
}

function reset_date() {
  my_date[0].value = "00" ;
  my_date[3].value = "00" ;
  my_date[6].value = "" ;
}

function get_param_value(me) {
  var param_value = "";
  if (me.options) {
    for (i=0; i<me.options.length; i++) {
      if (me.options[i].selected) {
        if (param_value.length!=0) {
          param_value += ',';
        }
        param_value += me.options[i].value;
      }
    }
  }
  else {
    param_value = me.value;
  }
  if (param_value.length!=0) {
    param_value = me.name+'='+param_value;
  }
  return param_value;
}

function set_param() {
  form_action = "";
  for (p=0; p<my_param.length; p++) {
    form_param = get_param_value(my_param[p]);
    if (form_param.length!=0) {
      if (form_action.length!=0) {
        form_action += '&';
      }
      form_action += form_param;
    }
  }
  if (form_action.length!=0) {
    form_action = '?'+form_action;
  }
  form_action = "http://meta.camptocamp.org/outings/query"+form_action;
  document.location.href = form_action;
  return true;
}
