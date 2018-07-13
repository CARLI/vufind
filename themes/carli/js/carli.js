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
       }
    // set it to local catalog, /vf-xxx
    } else {
       if (theaction.search(/\/all/) >= 0) {
          choiceObj.form.action = theaction.replace("/all/vf-", "/vf-");
       }
    }
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


});
