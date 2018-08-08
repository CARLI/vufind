// global: map gets populated in templates/RecordTab/holdingsils.phtml
var vol2Bibs = [];
// global: number of total holdings
var vol2BibsHoldings = 0;
// global: patron's home library
var patronHomeLibrary;

var checkRequestableItemStatusIds = [];
var checkRequestableItemStatusIdsUsedAlready = [];
var checkRequestableItemStatusEls = {};
var checkRequestableItemStatusURLs = [];
var checkRequestableItemStatusTimer = null;
var checkRequestableItemStatusDelay = 200;
var checkRequestableItemStatusRunning = false;
var copyVolumes = [];
var copyVolumesListToString = null;
var lastCopyVolumesListToString = null;
var statusText = [];

function resetCheckRequestableParameters() {
  checkRequestableItemStatusIds = [];
  checkRequestableItemStatusEls = {};
  checkRequestableItemStatusURLs = [];
  checkRequestableItemStatusRunning = false;
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
  // Only run one item status AJAX request at a time:
  if (checkRequestableItemStatusRunning) {
    clearTimeout(checkRequestableItemStatusTimer);
    checkRequestableItemStatusTimer = setTimeout(runCheckRequestableItemAjaxForQueue, checkRequestableItemStatusDelay);
    return;
  }
  checkRequestableItemStatusRunning = true;
  $('#requestFirstAvailableButton').hide();

  var id;
  while (checkRequestableItemStatusIds.length > 0) {
    id  = checkRequestableItemStatusIds.shift();
    if (! id) {
      break;
    }
    if (checkRequestableItemStatusIdsUsedAlready.indexOf(id) < 0) {
      checkRequestableItemStatusIdsUsedAlready.push(id);
      break;
    } else {
      id = null;
    }
  }
  if (! id) {
    showStatusText('There are no more items left to check.<br/>');
    checkRequestableItemStatusRunning = false;
    $('#requestFirstAvailableButton').show();
    return;
  }
  var url = checkRequestableItemStatusURLs[id];
  var el = checkRequestableItemStatusEls[id];

  var sText= 'Checking ' + id + ' for ';
  // special case: "any item"
  if (copyVolumes.length === 1 && copyVolumes[0] === "") {
    sText+= 'available item';
  } else if (copyVolumes.length > 1) {
    sText+= ' items: ' + copyVolumes[0];
    for (var inx=1; inx<copyVolumes.length; inx++) {
      sText+= ', ' + copyVolumes[inx];
    }
  } else if (copyVolumes.length > 0) {
    sText+= ' item: ' + copyVolumes[0];
  }
  sText+= '...<br/>';
  showStatusText(sText);

  if (el.attr('href')) {
    //console.log("already checked for requestability...");
    showStatusText('Item was found.<br/>');
    el.trigger('click');
    checkRequestableItemStatusRunning = false;
    $('#requestFirstAvailableButton').show();
    return;
  }

  ///////////////////////////////////////////////////////
  var vars = deparam(el.attr('hiddenHref'));
  vars.id = id;

  var requestType = 'ILLRequest';
  if (id.startsWith(patronHomeLibrary + '.')) {
    requestType = 'StorageRetrievalRequest';
  }

  var url = VuFind.path + '/AJAX/JSON?' + $.param({
    method: 'checkRequestIsValid',
    id: id,
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
      showStatusText('Item was found.<br/>');

      // set href to hiddenHref value so that when clicked it works
      el.attr('href', el.attr('hiddenHref'));

      el.trigger('click');
      checkRequestableItemStatusRunning = false;
      $('#requestFirstAvailableButton').show();
    } else {
      showStatusText('Item not requestable from this holding. Trying the next one...<br/>');
      checkRequestableItemStatusRunning = false;
      clearTimeout(checkRequestableItemStatusTimer);
      checkRequestableItemStatusTimer = setTimeout(runCheckRequestableItemAjaxForQueue, checkRequestableItemStatusDelay);
    }
  })
  .fail(function checkValidFail(/*response*/) {
    showStatusText('Item not requestable from this holding. Trying the next one...<br/>');
    checkRequestableItemStatusRunning = false;
    clearTimeout(checkRequestableItemStatusTimer);
    checkRequestableItemStatusTimer = setTimeout(runCheckRequestableItemAjaxForQueue, checkRequestableItemStatusDelay);
  });
  ///////////////////////////////////////////////////////
}

function checkRequestableItemQueueAjax(el) {
  var id = el.attr('hiddenId');
  var url = el.attr('hiddenHref');
  if (id.length < 1 || url.length < 1) {
    return;
  }
  // only add bibs that contain this copy/volume; skip all others
  var validBib = true;
  var itemId = null;
  // special case: "any item" has empty value
  if (copyVolumes.length === 1 && copyVolumes[0] === "") {
    // do nothing
  } else if (copyVolumes.length > 0) {
    for (var inx=0; inx<copyVolumes.length; inx++) {
      var copyVolume = copyVolumes[inx];
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
                   //console.log('thisCopyVolume=' + thisCopyVolume + ' matches bib=' + bib + '; itemId=' + itemId + ' matches urlItemId=' + urlItemId);
                   break;
                }
              }
            }
          }
        }
        if (validBib) {
          break;
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

  copyVolumes = $('#checkRequestableItem').val();
  if (copyVolumes == null) {
    copyVolumes = [];
  }
  copyVolumesListToString = "";
  for (var inx=0; inx<copyVolumes.length; inx++) {
    copyVolumesListToString += copyVolumes[inx];
  }

  if (lastCopyVolumesListToString != null && copyVolumesListToString !== lastCopyVolumesListToString) {
    // clear out previously-used cache
    checkRequestableItemStatusIdsUsedAlready = [];
  }
  lastCopyVolumesListToString = copyVolumesListToString;
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
