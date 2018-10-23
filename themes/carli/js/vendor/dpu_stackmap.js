// integrated at http://www.library.uic.edu/

//var jQuery = jQuery.noConflict();

/*! Copyright (c) 2011 Brandon Aaron (http://brandonaaron.net)
 * Licensed under the MIT License (LICENSE.txt).
 *
 * Thanks to: http://adomas.org/javascript-mouse-wheel/ for some pointers.
 * Thanks to: Mathias Bank(http://www.mathias-bank.de) for a scope bug fix.
 * Thanks to: Seamus Leahy for adding deltaX and deltaY
 *
 * Version: 3.0.6
 *
 * Requires: 1.2.2+
 */


(function (a) { function d(b) { var c = b || window.event, d = [].slice.call(arguments, 1), e = 0, f = !0, g = 0, h = 0; return b = a.event.fix(c), b.type = "mousewheel", c.wheelDelta && (e = c.wheelDelta / 120), c.detail && (e = -c.detail / 3), h = e, c.axis !== undefined && c.axis === c.HORIZONTAL_AXIS && (h = 0, g = -1 * e), c.wheelDeltaY !== undefined && (h = c.wheelDeltaY / 120), c.wheelDeltaX !== undefined && (g = -1 * c.wheelDeltaX / 120), d.unshift(b, e, g, h), (a.event.dispatch || a.event.handle).apply(this, d) } var b = ["DOMMouseScroll", "mousewheel"]; if (a.event.fixHooks) for (var c = b.length; c;)a.event.fixHooks[b[--c]] = a.event.mouseHooks; a.event.special.mousewheel = { setup: function () { if (this.addEventListener) for (var a = b.length; a;)this.addEventListener(b[--a], d, !1); else this.onmousewheel = d }, teardown: function () { if (this.removeEventListener) for (var a = b.length; a;)this.removeEventListener(b[--a], d, !1); else this.onmousewheel = null } }, a.fn.extend({ mousewheel: function (a) { return a ? this.bind("mousewheel", a) : this.trigger("mousewheel") }, unmousewheel: function (a) { return this.unbind("mousewheel", a) } }) })(jQuery)
//

function Coordinate(startX, startY) {
    this.x = startX;
    this.y = startY;
}

function StackMapZoomMap(o) {
    var defaults = {
        // effects m.zoomMin and topEdge reset for our lockEdge functionality.
        // boxHeight: o.originalHeight || 510, // master
        boxHeight: o.windowHeight || o.originalHeight,
        // boxWidth: o.originalWidth || 680, // master
        // effects m.zoomMin and topEdge reset for our lockEdge functionality
        boxWidth: o.windowWidth || o.originalHeight,
        // container selector for placing map, handling click events ex: mousedown
        container: jQuery('#SMmap-container'),
        // value for when zoom fit is called to reset the x of img
        fitX: 0,
        // value for when zoom fit is called to reset the y of img, 
        // if set to 0, the left prop css will be changed to 0
        fitY: 0,
        // true moves the image while keeping the top left edge static
        // false moves the image for a given x and y param that can come from 
        // multiple sources, ex mousewheel or input params from zoom-in/zoom-out
        lockEdges: false,
        locXContents: '.loc-x',
        locYContents: '.loc-y',
        // map selector to add events to and alter css properties on.
        mapSelector: '.SMmap',
        // used to calc zoomMin, m.curSize (stored state of image size?)
        originalWidth: o.originalWidth || 800,
        originalHeight: o.originalHeight || 600,
        overlaySelector: '.SMmap-overlay',
        // style selector on the pin (or multiple)
        sizeXContents: '.size-x',
        sizeYContents: '.size-y',
        windowSelector: '.SMmap-window',
        // scale image grows
        zoomFactor: 1.1,
        // this is what we kind of set our default image value to
        // -1 or recommended between zoomMin & zoomMax
        zoomFit: 1, // if -1, then zoom all the way out (sets val to zoomMin)
        zoomFitBtn: '.zoom-fit',
        zoomInBtn: '.zoom-in',
        // image scale floor to zoom out (how small image can get)
        // *relative to zoomFactor
        zoomMin: 0.5,
        // image scale ceiling to zoom in (how large the image can get)
        zoomMax: 3, //adjust to max zoom in 
        zoomOutBtn: '.zoom-out',
        // padding size to provide extra boundary space when image is moved
        mapMovePadding: Math.min((o.windowWidth / 2), (o.windowHeight / 2))
    };
    var m = this;
    jQuery.extend(m, defaults, o);
    m.mapWindow = m.container.find(m.windowSelector);
    m.popupWindow = m.mapWindow.closest('.SMpopup');
    m.map = m.mapWindow.find(m.mapSelector);
    m.mapElem = m.map[0];
    m.overlay = m.mapWindow.find(m.overlaySelector);
    m.overlayElem = m.overlay[0];
    m.sizeXContents = m.overlay.find(m.sizeXContents);
    m.sizeYContents = m.overlay.find(m.sizeYContents);
    m.locXContents = m.overlay.find(m.locXContents);
    m.locYContents = m.overlay.find(m.locYContents);
    m.halfBoxHeight = m.boxHeight / 2;
    m.halfBoxWidth = m.boxWidth / 2;

    m.curZoomFactor = 1;
    m.zoomFactorElem = m.mapWindow.find(m.zoomFactorSelector)[0];
    // m.zoomMin = Math.min(m.boxWidth / m.originalWidth, m.boxHeight / m.originalHeight);

    if (m.zoomMin < 1)
        m.zoomMin = Math.pow(m.zoomFactor, (Math.floor(Math.log(m.zoomMin) / Math.log(m.zoomFactor))));

    if (m.zoomFit == -1)
        m.zoomFit = m.zoomMin;

    m.curSize = new Coordinate;
    m.mousePosition = new Coordinate;

    m.getWindowWidth = function () {
        m.mapWindowWidth = m.mapWindow.width();
        return m.mapWindowWidth;
    }

    m.getWindowHeight = function () {
        m.mapWindowHeight = m.mapWindow.height();
        return m.mapWindowHeight;
    }

    m.moveMap = function (x, y) {
        var newX = x, newY = y;
        if (m.lockEdges) {
            var rightEdge = -m.curSize.x + m.boxWidth;
            var topEdge = -m.curSize.y + m.boxHeight;
            newX = newX < rightEdge ? rightEdge : newX;
            newY = newY < topEdge ? topEdge : newY;
            newX = newX > 0 ? 0 : newX;
            newY = newY > 0 ? 0 : newY;
        }
        // right side (x) boundary
        if (newX > m.boxWidth - m.mapMovePadding) {
            m.mapElem.style.left = m.boxWidth - m.mapMovePadding + 'px';
            m.overlayElem.style.left = m.boxWidth - m.mapMovePadding + 'px';
        }
        // left side (x) boundary
        else if (newX < (-m.curSize.x) + m.mapMovePadding) {
            m.mapElem.style.left = (-m.curSize.x) + m.mapMovePadding + 'px';
            m.overlayElem.style.left = (-m.curSize.x) + m.mapMovePadding + 'px';
        }
        // default
        else {
            m.mapElem.style.left = newX + 'px';
            m.overlayElem.style.left = newX + 'px';
        }
        // upper side (y) boundary
        if (newY < (-m.curSize.y) + m.mapMovePadding) {
            m.mapElem.style.top = (-m.curSize.y) + m.mapMovePadding + 'px';
            m.overlayElem.style.top = (-m.curSize.y) + m.mapMovePadding + 'px';
        }
        // lower side (y) boundary
        else if (newY > m.boxHeight - m.mapMovePadding) {
            m.mapElem.style.top = m.boxHeight - m.mapMovePadding + 'px';
            m.overlayElem.style.top = m.boxHeight - m.mapMovePadding + 'px';
        }
        // default
        else {
            m.mapElem.style.top = newY + 'px';
            m.overlayElem.style.top = newY + 'px';
        }
    }

    m.setZoomFactor = function (newZoomFactor, focusX, focusY, shouldMove) {
        // helper to remove the move functionality when used sometimes.
        shouldMove = shouldMove || true;

        if (m.curZoomFactor == newZoomFactor) return;
        if (newZoomFactor < m.zoomMin) newZoomFactor = m.zoomMin;
        if (newZoomFactor > m.zoomMax) newZoomFactor = m.zoomMax;

        var mapPosition = m.map.position();
        var curFocusX = (-mapPosition.left + focusX) / m.curZoomFactor,
            curFocusY = (-mapPosition.top + focusY) / m.curZoomFactor;

        // update the tracking vars for new image size
        m.curZoomFactor = newZoomFactor;
        m.curSize.x = m.originalWidth * m.curZoomFactor;
        m.curSize.y = m.originalHeight * m.curZoomFactor;

        // change size of map
        m.mapElem.style.width = m.curSize.x + 'px';
        m.mapElem.style.height = m.curSize.y + 'px';

        m.sizeXContents.each(function () { jQuery(this).width(Math.ceil(jQuery(this).attr('width') * m.curZoomFactor)); });
        m.sizeYContents.each(function () { jQuery(this).height(Math.ceil(jQuery(this).attr('height') * m.curZoomFactor)); });

        m.locXContents.css('top', function () { return jQuery(this).attr('y') * m.curZoomFactor; });
        m.locYContents.css('left', function () { return jQuery(this).attr('x') * m.curZoomFactor; });

        curFocusX = focusX - (curFocusX * newZoomFactor);
        curFocusY = focusY - (curFocusY * newZoomFactor);
        if (shouldMove) m.moveMap(curFocusX, curFocusY);
    }

    m.mouseMove = function (event) {
        event.preventDefault();
        var e = event.pageX - m.mousePosition.x + m.map.position().left,
            d = event.pageY - m.mousePosition.y + m.map.position().top;
        m.moveMap(e, d);
        m.mousePosition.x = event.pageX;
        m.mousePosition.y = event.pageY;
    }

    m.map.mousedown(function (event) {
        event.preventDefault();
        m.mousePosition.x = event.pageX;
        m.mousePosition.y = event.pageY;

        jQuery(document).mousemove(m.mouseMove);
        jQuery(document).mouseup(function () { jQuery(document).unbind('mousemove'); });
    });

    m.resetMapByWidth = function () {
        m.boxHeight = m.mapWindow.height();
        m.boxWidth = m.mapWindow.width();
        var mapWidth = m.originalWidth;
        var ratio = (m.boxWidth / mapWidth);
        m.setZoomFactor(ratio, m.boxWidth / 2, m.boxHeight / 2, false);
    }

    var $zoomInBtn = m.container.find(m.zoomInBtn);
    var $zoomOutBtn = m.container.find(m.zoomOutBtn);

    $zoomInBtn.click(function () {
        var newZoomFactor = m.curZoomFactor * m.zoomFactor;
        zoomButtonGreyOut(newZoomFactor)
        m.setZoomFactor(newZoomFactor, m.halfBoxWidth, m.halfBoxHeight, false);
    });

    $zoomOutBtn.click(function () {
        var newZoomFactor = m.curZoomFactor / m.zoomFactor;
        zoomButtonGreyOut(newZoomFactor)
        m.setZoomFactor(newZoomFactor, m.halfBoxWidth, m.halfBoxHeight, false);
    });

    m.container.find(m.zoomFitBtn).click(function () {
        m.resetMapByWidth();
        m.moveMap(m.fitX, m.fitY);
        zoomButtonGreyOut('reset');
    });

    m.mapWindow.mousewheel(function (event, delta) {
        event.preventDefault();
        var mapPosition = m.mapWindow.position();
        var popupPosition = m.popupWindow.position();
        var newZoomFactor = m.curZoomFactor * Math.pow(m.zoomFactor, delta)

        zoomButtonGreyOut(newZoomFactor)
        m.setZoomFactor(newZoomFactor, event.pageX - mapPosition.left - popupPosition.left, event.pageY - mapPosition.top - popupPosition.top);
    });

    var zoomButtonGreyOut = function (newZoomFactor) {
        // hook for styling maxed out buttons
        var $zoomInCapped = $zoomInBtn.hasClass('sm-capped')
        var $zoomOutCapped = $zoomOutBtn.hasClass('sm-capped')
        if (newZoomFactor === 'reset') {
            if ($zoomInCapped) { $zoomInBtn.removeClass('sm-capped') };
            if ($zoomOutCapped) { $zoomOutBtn.removeClass('sm-capped') };
            return;
        }
        if (newZoomFactor <= m.zoomMin && !$zoomOutCapped) { $zoomOutBtn.addClass('sm-capped') }
        if (newZoomFactor > m.zoomMin && $zoomOutCapped) { $zoomOutBtn.removeClass('sm-capped') }
        if (newZoomFactor >= m.zoomMax && !$zoomInCapped) { $zoomInBtn.addClass('sm-capped') }
        if (newZoomFactor < m.zoomMax && $zoomInCapped) { $zoomInBtn.removeClass('sm-capped') }
    };

    m.resetMapByWidth();
    m.moveMap(m.fitX, m.fitY);
}


var StackMap = StackMap || {
    domain: 'https://depaul.stackmap.com',
    popupCounter: 0,
    delayImgLoad: true,
    libraries: [],
    locationHash: {
        "Law": "Law",
        "Lincoln": "Lincoln",
        "LincPk": "Lincoln",
        "Loop": "Loop"
    },
    setup: function () {
        //console.log('settingup..')
        jQuery("body").append('<div id="SMblock-screen"></div>');
        jQuery('#SMblock-screen').click(StackMap.hideAllPopups);
        jQuery("body").append('<div id="SMtooltip"><p></p></div>');

        jQuery(document)
            .on('mousedown', '.SMpin-target', function (e) {
                e.stopPropagation();
            })
            .on('mouseenter', '.SMpin-target', function (e) {
                jQuery('#SMtooltip')
                    .css('left', jQuery(this).offset().left + jQuery(this).width() + 5)
                    .css('top', jQuery(this).offset().top);
                jQuery('#SMtooltip p').html(
                    jQuery(this).find('.SMtooltip-contents').html());
                jQuery('#SMtooltip').fadeIn();
            })
            .on('mouseleave', '.SMpin-target', function (e) {
                jQuery('#SMtooltip').fadeOut();
            });

        jQuery(document).on('click', '.SMclose', StackMap.hideAllPopups);

        jQuery(document).on('click', '.SMprinter-friendly', function () {
            var $popup = jQuery(this).closest('.SMpopup');
            StackMap.openPrinterFriendly(
                $popup.data('callno'), $popup.data('location'),
                $popup.data('library'), $popup.data('title'));
        });

        StackMap.addPopups();
    },
    addPopups: function () {
        var request = { 'holding': [], 'alt': true }; // alt for condensed API
        var entries = [];

        // search view
        jQuery('.result-body').not('.sm-checked').each(function (i) {
            var $dataNode = jQuery(this);
            $dataNode.addClass('sm-checked')
            var status = $dataNode.find('.status').text().trim();
            if (status == 'Not Available') return;
            if (status == 'Live Status Unavailable') return;
            if (status === 'Loading...') {
                $dataNode.removeClass('sm-checked');
                return true;
            }
            var target = $dataNode.find('.location')

            var callno = $dataNode.find('.callnumber').text().trim()
            if (!callno) return;
            if (callno.indexOf('E-Serial') !== -1 || callno.toLowerCase().indexOf('offsite') !== -1 || callno === "E-Book") { return }

            var location = $dataNode.find('.location').text().trim();
            if (location.indexOf('offsite') !== -1 || location.indexOf('DePaul Electronic Access') !== -1) return;
            if (location.indexOf("Temporarily Shelved at: ") !== -1) {
                location = location.slice(28);
            }

            var temp = location.split(' ')[0];
            var library = StackMap.locationHash[temp];
            if (!library) return;
    
            var holdingString = library + '$$' + location + '$$' + callno;
            request.holding.push(holdingString);
            entries.push(jQuery(target));
        })
        //detail view
        jQuery(".tab-content").not('.sm-checked').each(function (i) {
            var $dataNode = jQuery(this)
            $dataNode.addClass('sm-checked')

            var location = $dataNode.find('h4').text().trim();
            if (location.toLowerCase().indexOf('offsite') !== -1) return;
            if (location.indexOf('Location: ') > -1) {
                location = location.slice(10);
            }
            if (location.indexOf("Temporarily Shelved at: ") !== -1) {
                location = location.slice(28);
            }

            var callno = $dataNode.find('tbody tr').eq(0).find('td').text().trim()
            if (callno.indexOf('E-Serial') !== -1 ||
                callno.indexOf('Offsite Shelving') !== -1 ||
                callno === "E-Book"
                ) { return }
            // if ( callno.split(' ')[0] === 'MICR.') { 
            //     callno = callno.split(' ').slice(1).join(' ')  
            // } 
            // if (location.toLowerCase().indexOf('law' ) !== -1) {
            //      library = 'Rinn Law Library'  
            // }
            var target = $dataNode.find('tbody tr').eq(1).find('td')
            var temp = location.split(' ')[0];
            var library = StackMap.locationHash[temp];
            if (!library) return;

            var holdingString = library + '$$' + location + '$$' + callno;
            // console.log({holdingString})
            request.holding.push(holdingString);
            entries.push(target);
        });

        if (request.holding.length == 0) return;
        StackMap.requestHoldingData(entries, request.holding, StackMap.domain)
    },
    partitionQueriesAndSend: function (payload) {
        Object.keys(payload).forEach(function (url, i) {
            var entries = payload[url].entries
            var holding = payload[url].request.holding
            StackMap.requestHoldingData(entries, holding, url)
        })
    },
    requestHoldingData: function (entries, holdings, targetUrl) {
        jQuery.ajax({
            dataType: "json",
            url: targetUrl + "/json/?callback=?",
            timeout: 5000,
            data: { holding: holdings, alt: true },
            success: function (data, textStatus) {
                // console.log({ data, textStatus })
                for (var i = 0; i < entries.length; i++) {
                    var holdingTitle = jQuery('h1').eq(1).html();

                    var result = data.results[i];

                    var btnContainer = jQuery('<div class="btn-container"></div>').append(
                        jQuery('<a href="#" style="text-decoration: none"><i class="fa fa-print fa-1x SMprinter-friendly " aria-hidden="true"></i></a>'),
                        jQuery('<a href="#" style="text-decoration: none"><i class="fa fa-times SMclose fa-1x" aria-hidden="true"></i></a>'));

                    if (result.maps.length != 0) { //if it was successful...
                        map = result.maps[0];
                        var $popup = jQuery('<div>', {
                            'class': 'SMpopup',
                            'id': 'SM' + StackMap.popupCounter,
                            'data-callno': result.callno,
                            'data-location': result.location,
                            'data-library': result.library,
                            'data-title': holdingTitle
                        })
                            .append(
                                // jQuery('<a href="#" style="text-decoration: none"><i class="fa fa-times SMclose fa-1x SMicon" aria-hidden="true" style="margin-right: 5px"></i></a>'),
                                // jQuery('<a href="#" style="text-decoration: none"><i class="fa fa-print fa-1x SMprinter-friendly SMicon " aria-hidden="true" style="margin-right: 15px"></i></a>'),
                                btnContainer,
                                jQuery('<div class="SMheader"><h2 >' + map.library + ', ' + map.floorname + '</h2></div>')
                            );

                        var $mapImg = jQuery('<img />', { 'class': 'SMmap', alt: map.floorname });
                        if (StackMap.delayImgLoad) {
                            $mapImg.attr('othersrc', map.mapurl + '&marker=1');
                        } else {
                            $mapImg.attr('src', map.mapurl + '&marker=1');
                        }

                        var $map = jQuery('<div>', { 'class': 'SMmap-container' }).append(
                            jQuery('<ul>', { 'class': 'SMmap-buttons' }).append(

                                jQuery('<li> <a class="zoom-in" href="javascript:void(0);" style="display: inline-block; position: relative; text-decoration: none;"><i class="fa fa-plus-circle SMicon fa-1x" aria-hidden="true"></i><span> zoom in </span></a></li>'),
                                jQuery('<li> <a class="zoom-out" href="javascript:void(0);" style="display: inline-block; position: relative; text-decoration: none;"><i class="fa fa-minus-circle SMicon fa-1x" aria-hidden="true"></i><span> zoom out</span></a></li>'),
                                jQuery('<li> <a class="zoom-fit" href="javascript:void(0);" style="display: inline-block; position: relative; text-decoration: none;"><i class="fa fa-arrows SMicon fa-1x" aria-hidden="true"></i><span> Entire Map</span></a></li>')
                            ),
                            jQuery('<div>', { 'class': 'SMmap-window', style: 'width: 680px; height: 510px; border: 1px rgb(230,230,230) solid' }).append(
                                $mapImg,
                                jQuery('<div>', { 'class': 'SMmap-overlay' })
                            )
                        );

                        for (var j = 0; j < map.ranges.length; j++) { //bubble text
                            var callnoText = 'Range ' + map.ranges[j].rangename + '<br />';
                            if (map.ranges[j].startcallno != '*') {
                                callnoText += map.ranges[j].startcallno + ' -<br /> ' + map.ranges[j].endcallno;
                            }

                            var callnoX = map.ranges[j].x - 10;
                            var callnoY = map.ranges[j].y - 45;

                            $map.find('.SMmap-overlay').append(jQuery('<div>',
                                {
                                    'class': 'SMpin-target loc-x loc-y size-x size-y',
                                    x: callnoX,
                                    y: callnoY,
                                    style: 'left:' + callnoX + 'px; top:' + callnoY + 'px;"'
                                })
                                .html('&nbsp;')
                                .attr('height', 44).attr('width', 25)
                                .append(jQuery('<div>',
                                    { 'class': 'SMtooltip-contents', style: 'display:none;' })
                                    .html(callnoText)
                                )
                            );
                        }

                        var $sidebar = jQuery('<div>', { 'class': 'SMmore-info' });

                        var range = map.ranges[0].rangename;

                        var $sidebarContents = jQuery('<ul>').append(
                            /*jQuery('<li><p><strong>Directions:</strong></p></li>'),
                            jQuery('<li><p class="SMemph">' + map.directions + '</p></li>'),
                            jQuery('<li>This pin <i class="fa fa-map-marker SMdirections" aria-hidden="true"></i> indicates your item\'s location on the map.<br /></li><br>'),
                            jQuery('<li><p>Go to the shelving row labeled:</p></li>'),
                            jQuery('<li ><p class="SMemph">' + range + '</p></li><br>'),
                            jQuery('<li><p>Look for the item with this call number: </p></li>'),
                            jQuery('<li><p class="SMemph">' + result.callno + '</p></li>')
                       */
                            jQuery('<li>This pin <i class="fa fa-map-marker SMdirections" aria-hidden="true"></i> indicates your item\'s location on the map.<br /></li><br>'),
                            jQuery('<li><p> 1. <p class="SMemph">' + map.directions + '</p></p></li><br>'),
                            jQuery('<li><p> 2. Find the row labeled: <p class="SMemph">' + range + '</p></p></li><br>'),
                            jQuery('<li><p> 3. Look for the item with this call number: <p class="SMemph">' + result.callno + '</p></p></li>')
                        );

                        $sidebar.append($sidebarContents);

                        $popup.append($map, $sidebar);
                        $popup.append(jQuery('<span class="SMpowered-by">Powered by <a style="color: rgb(27,79,161);" target="_blank" href="http://stackmap.com">stackmap.com</a></span>'));
                        jQuery("body").append($popup);


                        var mapZoomer = new StackMapZoomMap(
                            {
                                container: $popup.find('.SMmap-container'),
                                originalWidth: map.width, originalHeight: map.height
                            });

                        entries[i].append('<button class=" SMsearchbtn" type="button" onclick="StackMap.showPopup(\'SM' + StackMap.popupCounter + '\');" ><i class="fa fa-map-marker" aria-hidden="true"></i> Map</button>');
                        // jQuery('<button class=" SMsearchbtn" type="button" onclick="StackMap.showPopup(\'SM' + StackMap.popupCounter + '\');" ><i class="fa fa-map-marker" aria-hidden="true"></i> Map</button>').insertAfter(entries[i]);
                        StackMap.popupCounter++;
                    }
                }
            }
        });

    },
    openPrinterFriendly: function (callno, location, library, title) {
        var pfUrl = StackMap.domain + '/view/?callno=' + callno + '&amp;location=' + location.replace('&amp;', '%26') + '&amp;library=' + library + '&amp;title=' + title + '&amp;v=pf';
        window.open(pfUrl, 'stackmap', 'width=950,height=800,toolbar=no,directories=no,scrollbars=1,location=no,menubar=no,status=no,left=0,top=0');
        return false;
    },
    showPopup: function (popupId) {
        var $popup = jQuery('#' + popupId);

        if (!$popup.data('opened')) {
            if (StackMap.delayImgLoad) {
                var $mapImg = $popup.find('.SMmap');
                $mapImg.attr('src', $mapImg.attr('othersrc'));
            }
            var postData = {
                callno: $popup.data('callno'),
                library: $popup.data('library'),
                location: $popup.data('location'),
                action: 'mapit'
            };
            jQuery.getJSON(StackMap.domain + "/logmapit/?callback=?", postData);
            $popup.data('opened', true);
        }

        // var left = Math.max(0, (jQuery(window).width() - 890) / 2 + jQuery(window).scrollLeft());
        $popup.css("top", (jQuery(window).scrollTop() + 10) + "px")
            //.css("left", left + "px")
              .css("display", "flex");

        jQuery('#SMblock-screen').css('height', jQuery(document).height()).show();
    },
    hideAllPopups: function () {
        jQuery('.SMpopup').hide();
        jQuery('#SMblock-screen').hide();
    }
}

jQuery(document).ready(function () {
    StackMap.setup();
    setInterval(StackMap.addPopups, 3000);
});
