function getURLParameter(name) {
    return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(location.search) || [null, ''])[1].replace(/\+/g, '%20')) || null;
}

function autoLoginCarli() {
    forceMethod = getURLParameter('auth_method');
    // allow override
    if (forceMethod) {
        return;
    }
    if(document.getElementById('carli-login-link')!=null||document.getElementById('carli-login-link')!=""){ 
        hideLoginPageCarli();
        document.getElementById('carli-login-link').click();
    }
}

function hideLoginPageCarli() {
    if(document.body!=null||document.body!=""){ 
        document.body.style.display = 'none';
    }
}

function switchCatalog(choiceObj) {
    var theaction = choiceObj.form.action;
    var thiscatalog = choiceObj.selectedIndex;

    // set it to All I-Share, /all/vf-xxx
    if (thiscatalog == 0) {
       if (theaction.search(/\/all/) < 0) {
          choiceObj.form.action = theaction.replace("/vf-", "/all/vf-");

          // remove these filters when switching to I-Share catalog
          $('.applied-filter').each(function(){
              var collection = $(this).attr('value');
              if (collection.match(/^collection:/)) {
                  $(this).prop('checked', false);
              }
          });
       }
    // set it to local catalog, /vf-xxx
    } else {
       if (theaction.search(/\/all/) >= 0) {
          choiceObj.form.action = theaction.replace("/all/vf-", "/vf-");

          // remove these filters when switching to local catalog
          $('.applied-filter').each(function(){
              var collection = $(this).attr('value');
              if (collection.match(/^institutions:/)) {
                  $(this).prop('checked', false);
              }
          });
       }
    }
}

function carli_checkRequestIsValid(element, requestType) {
  // force user to login - otherwise, we will give them false indication of item not being requestable
  // also: we need to make sure the logged-in user is also profiled to backend ILS (patronHomeLibrary must be valid!)
  if (!userIsLoggedIn || !patronHomeLibrary) {
      var loginURL = window.location.toString();
      if (loginURL.indexOf("?") > 0) {
          loginURL = loginURL.substring(0, loginURL.indexOf("?"));
      }
      if (loginURL.indexOf("#") > 0) {
          loginURL = loginURL.substring(0, loginURL.indexOf("#"));
      }
      loginURL += '?login=true&catalogLogin=true';
      window.location = loginURL;
      return;
  }

  // if disabled, we are performing check now
  // if hiddenHref -> href, we already performed check
  if ($(element).attr('disabled') || $(element).attr('href')) {
    return;
  }
  $(element).attr('disabled', 'disabled')
  $(element)
    .addClass('disabled')
    .attr('title', 'Checking...')
    .html('<i class="fa fa-flag" aria-hidden="true"></i>&nbsp;' + 'Checking...');

  var href = $(element).attr('hiddenHref');
  var recordId = href.match(/\/Record\/([^\/]+)\//)[1];
  var vars = deparam(href);
  vars.id = recordId;

  if (recordId.indexOf(patronHomeLibrary + '.') == 0) { // IE
    requestType = 'StorageRetrievalRequest';
  }
  //console.log('carli_checkRequestIsValid requestType = ' + requestType);

  var url = VuFind.path + '/AJAX/JSON?' + $.param({
    method: 'checkRequestIsValid',
    id: recordId,
    requestType: requestType,
    data: vars
  });
  $.ajax({
    dataType: 'json',
    cache: false,
    url: url
  })
  .done(function checkValidDone(response) {
    if (response.data.status) {
      $(element).removeAttr('disabled');
      $(element).removeClass('disabled')
        .attr('title', response.data.msg)
        .html('<i class="fa fa-flag" aria-hidden="true"></i>&nbsp;' + response.data.msg);
        //.html('<i class="fa fa-flag" aria-hidden="true"></i>&nbsp;' + 'Place a Request');

      // set href to hiddenHref value so that when clicked it works
      $(element).attr('href', href);
    } else {
      $(element).removeAttr('disabled');
      $(element).removeClass('disabled')
        .attr('title', 'Item cannot be requested')
        .html('<i class="fa fa-flag" aria-hidden="true"></i>&nbsp;' + 'Item cannot be requested');
    }
  })
  .fail(function checkValidFail(/*response*/) {
    //$(element).remove();
    $(element).removeAttr('disabled');
    $(element).removeClass('disabled')
      .attr('title', 'Item cannot be requested')
      .html('<i class="fa fa-flag" aria-hidden="true"></i>&nbsp;' + 'Item cannot be requested');
  });
}


$(document).ready(function() {

  // Text for various states
  var viewItemsText = 'View more items <i class="fa fa-arrow-circle-right"></i>';
  var hideItemsText = 'Hide items <i class="fa fa-arrow-circle-down"></i>';

  var viewItems = '<tr><td colspan="2"><a href="#" class="itemsToggle text-success">' + viewItemsText + '</a></td></tr>';
  var hideItems = '<a href="#" class="itemsToggle text-success">' + hideItemsText + '</a>';

  $('.carli-holdings-unit').each(function(){
    // Number of things to display by default
    var displayNum = $(this).data('carli-display');

    // Boolean, is there a toggle link appended
    var hasLink = false;

    // Build toggle link
    //$(this).parent().append(viewItems);

    // Loop over items
    var i = 1;
    $(this).find('[typeof="Offer"]').each(function(){
      // Provide a target for the items toggle link.
      // We'll append a link here if we need one.
      if (i == displayNum) {
          $(this).addClass('itemsToggleTarget');
      }
      // Hide items and append a class when there are more than the allowed quantity
      if (i > displayNum) {
          $(this).hide();
          $(this).addClass('toToggle');
          // Show the view link if possible
          //$(this).parent().parent().parent().find('.itemsToggle').removeClass('hide');

          // If there isn't already a link
          if(!hasLink > 0 ){
              $(this).parent().find('.itemsToggleTarget').after(viewItems);
              hasLink = true;
          }
        }
      i++;
    });
  });

  //prevent links from making the page jump
  $('a[href^="#"]').bind('click focus', function(e) {
      e.preventDefault();    var viewItems = '<a href="#" class="itemsToggle text-success hide">' + viewItemsText + '</a>';
      var hideItems = '<a href="#" class="itemsToggle text-success">' + hideItemsText + '</a>';
  });

  // Toggle hidden items
  $('.itemsToggle').click(function(){
    // Toggle the inner html of the link
    var text = $(this).html();
    if (text == viewItemsText) {
        text = hideItemsText;
    }
    else {
        text = viewItemsText;
    }
    $(this).html(text);

    // Show/hide items 
    $(this).parent().parent().parent().find('tr.toToggle').toggle();
  });

  $('.checkILLRequest').click(function checkILLRequest(e) {
    carli_checkRequestIsValid(this, 'ILLRequest');
  });

  // if SFX links becomes active, hide the 'open text' links
  $('img[data-recordid]').load(function() {
    if ($(this).height() > 1) {
      var thisId = $(this).data('recordid');
      $('div[data-recordid="' + thisId + '"]').hide();
    }
  });


});
