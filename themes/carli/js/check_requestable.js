// global: map gets populated in templates/RecordTab/holdingsils.phtml
var vol2Bibs = [];
// global: number of total holdings
var vol2BibsHoldings = 0;

var checkRequestableItemStatusIds = [];
var checkRequestableItemStatusEls = {};
var checkRequestableItemStatusURLs = [];
var checkRequestableItemStatusTimer = null;
var checkRequestableItemStatusDelay = 200;
var checkRequestableItemStatusRunning = false;
var firstItemMatch = true;
var copyVolume = null;
var lastCopyVolume = null;
var statusText = [];

function resetCheckRequestableParameters() {
  checkRequestableItemStatusIds = [];
  checkRequestableItemStatusEls = {};
  checkRequestableItemStatusURLs = [];
  checkRequestableItemStatusRunning = false;
  firstItemMatch = true;
  statusText = [];
}

function getParameterFromUrl(url, name) {
    return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(url) || [null, ''])[1].replace(/\+/g, '%20')) || null;
}


function showStatusText(msg) {
  statusText.push(msg);
  $('#checkRequestableStatus').html(statusText);
}

function checkRequestableItemStatusFail(response, textStatus) {
  if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') {
    return;
  }
  showStatusText('Request failed!<br/>');
}

function runCheckRequestableItemAjaxForQueue() {
  // ran out of Ids to check:
  if (checkRequestableItemStatusIds.length < 1) {
    showStatusText('There are no more items left to check.<br/>');
    $('#requestFirstAvailableButton').show();
    return;
  }
  // Only run one item status AJAX request at a time:
  if (checkRequestableItemStatusRunning) {
    clearTimeout(checkRequestableItemStatusTimer);
    checkRequestableItemStatusTimer = setTimeout(runCheckRequestableItemAjaxForQueue, checkRequestableItemStatusDelay);
    return;
  }
  checkRequestableItemStatusRunning = true;

  var id = checkRequestableItemStatusIds.shift();
  var url = checkRequestableItemStatusURLs[id];
  var el = checkRequestableItemStatusEls[id];

  showStatusText('Trying ' + id + '<br/>');

  $.ajax({
    dataType: 'html',
    method: 'GET',
    url: url,
  })
  .done(function checkRequestableItemStatusDone(response) {
    // this is our test of a requestable item (pickUpLibrary variable is in the HTML response)
    if (/gatheredDetails\[pickUpLibrary\]/.test(response)) {
      showStatusText('Request succeeded!<br/>');
      $('#requestFirstAvailableButton').show();

      el.trigger('click');
      checkRequestableItemStatusRunning = false;
    } else {
      showStatusText('Request failed. Trying again...<br/>');
      checkRequestableItemStatusRunning = false;
      clearTimeout(checkRequestableItemStatusTimer);
      checkRequestableItemStatusTimer = setTimeout(runCheckRequestableItemAjaxForQueue, checkRequestableItemStatusDelay);
    }
  })
  .fail(function checkRequestableItemStatusFail(response, textStatus) {
      showStatusText('Request failed. Trying again...<br/>');
      checkRequestableItemStatusRunning = false;
      clearTimeout(checkRequestableItemStatusTimer);
      checkRequestableItemStatusTimer = setTimeout(runCheckRequestableItemAjaxForQueue, checkRequestableItemStatusDelay);
  });
}

function checkRequestableItemQueueAjax(el) {
  var id = el.attr('hiddenId');
  var url = el.attr('href');
  if (id.length < 1 || url.length < 1) {
    return;
  }
  // only add bibs that contain this copy/volume; skip all others
  var validBib = true;
  var itemId = null;
  if (copyVolume) {
    validBib = false;
    //console.log('copyVolume is ' + copyVolume);
    if (vol2Bibs[copyVolume]) {
       for (var i=0; i<vol2Bibs[copyVolume].length; i++) {
         var thisCopyVolume = vol2Bibs[copyVolume][i] 
         //console.log('thisCopyVolume= ' + thisCopyVolume);
         var bibParts = thisCopyVolume.split('.');
         if (bibParts.length > 1) {
           var bib = bibParts[0] + '.' + bibParts[1];
           if (bib === id) {
             //console.log('bib matches: ' + bib);
             if (bibParts.length > 2) {
               itemId = bibParts[2];
               var urlItemId = getParameterFromUrl(url, 'item_id');
               if (itemId === urlItemId) {
                 validBib = true;
                 if (firstItemMatch) {
                   showStatusText('Item ' + copyVolume + '<br/>');
                   firstItemMatch = false;
                 }
                 //console.log('thisCopyVolume=' + thisCopyVolume + ' matches bib=' + bib + '; itemId=' + itemId + ' matches urlItemId=' + urlItemId);
                 break;
              }
            }
          }
        }
      }
    }
  }
  url = window.location.protocol + window.location.port + '//' + window.location.hostname  + url;
  // only one per bib, e.g., XXXdb.123
  if (validBib && checkRequestableItemStatusIds.indexOf(id) < 0) {
    checkRequestableItemStatusIds.push(id);
    checkRequestableItemStatusEls[id] = el;
    checkRequestableItemStatusURLs[id] = url;
  }
  clearTimeout(checkRequestableItemStatusTimer);
  checkRequestableItemStatusTimer = setTimeout(runCheckRequestableItemAjaxForQueue, checkRequestableItemStatusDelay);
}

function checkRequestableItems(_container) {
  var container = _container instanceof Element
    ? _container
    : document.body;

  $('#requestFirstAvailableButton').hide();

  copyVolume = $('#checkRequestableItem').val();
  resetCheckRequestableParameters();

  var ajaxItems = $(container).find('.ajaxCheckRequestableItem');
  for (var i = 0; i < ajaxItems.length; i++) {
    checkRequestableItemQueueAjax($(ajaxItems[i]));
  }
}

$(document).ready(function checkRequestableItemStatusReady() {
  var ajaxItems = $(document.body).find('.ajaxCheckRequestableItem');
  if (ajaxItems.length < 1) {
    $('#requestFirstAvailableButton').hide();
  } else {
    $('#requestFirstAvailableButton').show();
  }
});
