(function(win) {

  window.fullCalendars = [];

  var int_reg = /^\d+$/;

  function underscoreToUpper(s) {
    // event_limit ==> eventLimit
    return s.replace(/_([a-z])/g, function (g) { return g[1].toUpperCase(); });
  }

  // Because attributes are always strings, we need to cast them to appropriate types.
  function castAttrValue(value, defaultValue) {
    if (value === 'true') return true;
    if (value === 'false') return false;
    if (int_reg.test(value)) {
      return parseInt(value, 10);
    }
    if (!value && typeof defaultValue !== "undefined") {
      return defaultValue;
    }
    return value;
  }

  function getConfigBackgroundColor(config) {
    if ("eventBackgroundColor" in config) {
      return config.eventBackgroundColor;
    }
    if ("eventColor" in config) {
      return config.eventColor;
    }
    return false;
  }

  function padDatePart(d) {
    if (d < 10) return "0" + d.toString();
    return d;
  }

  function dateFormat(date) {
    return date.getFullYear() + "-" + padDatePart(date.getMonth() + 1) + "-" + padDatePart(date.getDate());
  }

  function castObjectAttributes(obj) {
    Object.keys(obj).forEach(function(key) {
      if (obj[key]) {
        switch (typeof obj[key]) {
          case 'string':
            obj[key] = castAttrValue(obj[key]);
            break;
          case 'object':
            if (obj[key].constructor === Object) {
              castObjectAttributes(obj[key]);
            }
            break;
        }
      }
    });
  }
  
  Array.prototype.forEach.call(document.querySelectorAll(".pgc-calendar-wrapper"), function(calendarWrapper, calendarCounter) {

    var currentAllEvents = null;
    var fullCalendar = null;
    var $calendar = calendarWrapper.querySelector('.pgc-calendar');
    var $calendarFilter = calendarWrapper.querySelector('.pgc-calendar-filter');

    var selectedCalIds = null;
    var allCalendars = null;

    // fullCalendar locales are like this: nl-be OR es
    // The locale we get from WP are en_US OR en.
    var locale = 'en-us';
    if ($calendar.getAttribute('data-locale')) {
      locale = $calendar.getAttribute('data-locale').toLowerCase().replace("_", "-"); // en-us or en
    }

    // Always present, gets set in PHP file.
    // Note: make sure you use the same defaults as get set in the PHP file!
    var filter = castAttrValue($calendar.getAttribute('data-filter'));
    var showEventPopup = castAttrValue($calendar.getAttribute('data-eventpopup'), true);
    var showEventLink = castAttrValue($calendar.getAttribute('data-eventlink'), false);
    var hidePassed = castAttrValue($calendar.getAttribute('data-hidepassed'), false);
    var hideFuture = castAttrValue($calendar.getAttribute('data-hidefuture'), false);
    var showEventDescription = castAttrValue($calendar.getAttribute('data-eventdescription'), false);
    var showEventLocation = castAttrValue($calendar.getAttribute('data-eventlocation'), false);
    var showEventAttendees = castAttrValue($calendar.getAttribute('data-eventattendees'), false);
    var showEventAttachments = castAttrValue($calendar.getAttribute('data-eventattachments'), false);
    var showEventCreator = castAttrValue($calendar.getAttribute('data-eventcreator'), false);
    var showEventCalendarname = castAttrValue($calendar.getAttribute('data-eventcalendarname'), false);

    // This can be overridden by shortcode attributes.
    var defaultConfig = {
      height: "auto",
      locale: locale,
      eventLimit: true
    };
    var dataConfig = $calendar.getAttribute("data-config") ? JSON.parse($calendar.getAttribute("data-config")) : {};
    
    // Cast booleans and int (we also get these as strings)
    castObjectAttributes(dataConfig);

    var config = Object.assign({}, defaultConfig);
    Object.keys(dataConfig).forEach(function(key) {
      var value = castAttrValue(dataConfig[key]);
      config[underscoreToUpper(key)] = value;
    });

    // Users can set specific set of calendars
    // Only in widget set (data-calendarids)
    var thisCalendarids = $calendar.getAttribute('data-calendarids') ? JSON.parse($calendar.getAttribute('data-calendarids')) : [];
    // Only in shortcode
    if ("calendarids" in config) {
      thisCalendarids = config.calendarids.split(",").map(function(item) {
        return item.replace(" ", "");
      });
    }

    function handleCalendarFilter(calendars) {

      allCalendars = calendars;
      
      if (selectedCalIds !== null) {
        return;
      }
      
      selectedCalIds = Object.keys(calendars); // default all calendars selected
      
      if (!filter) return;
      
      var selectBoxes = [];
      Object.keys(calendars).forEach(function(key, index) {
        if (thisCalendarids.length && thisCalendarids.indexOf(key) === -1) {
          return;
        }
        selectBoxes.push('<input id="id_' + calendarCounter + '_' + index + '" type="checkbox" checked value="' + key + '" />'
          + '<label for="id_' + calendarCounter + '_' + index + '">'
          + '<span class="pgc-calendar-color" style="background-color:' + (getConfigBackgroundColor(config) || calendars[key].backgroundColor) + '"></span> ' + calendars[key].summary
          + '</label>');
      });
      $calendarFilter.innerHTML = '<div class="pgc-calendar-filter-wrapper">' + selectBoxes.join("\n") + '</div>';
    }

    function getFilteredEvents() {
      var newEvents = [];
      currentAllEvents.forEach(function(item) {
        if (selectedCalIds.indexOf(item.calId) > -1) {
          newEvents.push(item);
        }
      });
      return newEvents;
    }

    function setEvents() {
      var newEvents = getFilteredEvents();
      var calendarEvents = fullCalendar.getEvents();
      fullCalendar.batchRendering(function() {
        calendarEvents.forEach(function(e) {
          e.remove();
        });
      });
      fullCalendar.batchRendering(function() {
        newEvents.forEach(function(e) {
          fullCalendar.addEvent(e);
        });
      });
    }

    $calendarFilter.addEventListener("change", function() {
      selectedCalIds = Array.prototype.map.call(calendarWrapper.querySelectorAll(".pgc-calendar-filter-wrapper input[type='checkbox']:checked"), function(item) {
        return item.value;
      });
      setEvents();
    });

    // Add things no one can override.
    config = Object.assign(config, {
      loading: function(isLoading, view) {
        if (isLoading) {
          calendarWrapper.classList.remove("pgc-loading-error");
          calendarWrapper.classList.add("pgc-loading");
        } else {
          calendarWrapper.classList.remove("pgc-loading");
        }
      },
      //eventClick: function(calEvent, jsEvent, view) {
        // now handled by tippy tooltips.
      //},
      eventRender: function(info) {

        console.log(info.event.title, info.event.start);


        //console.log(info.event.start.toLocaleString() + " --- " + info.event.start.toString(), info.event);

        if (showEventPopup) {
          var texts = ['<span class="pgc-popup-draghandle dashicons dashicons-screenoptions"></span><div class="pgc-popup-row pgc-event-title"><div class="pgc-popup-row-icon"><span></span></div><div class="pgc-popup-row-value">' + info.event.title + '</div></div>'];

          texts.push('<div class="pgc-popup-row pgc-event-time"><div class="pgc-popup-row-icon"><span class="dashicons dashicons-clock"></span></div><div class="pgc-popup-row-value">' + info.event.start.toLocaleDateString() + '<br>');
          if (info.event.allDay) {
            texts.push("All day</div></div>");
          } else {
            texts.push(info.event.start.toLocaleTimeString(locale, {
              timeStyle: "short"
            }) + " - " + info.event.end.toLocaleTimeString(locale, {
              timeStyle: "short"
            }) + "</div></div>");
          }
          if (showEventDescription && info.event.extendedProps.description) {
           texts.push('<div class="pgc-popup-row pgc-event-description"><div class="pgc-popup-row-icon"><span class="dashicons dashicons-editor-alignleft"></span></div><div class="pgc-popup-row-value">' + info.event.extendedProps.description + '</div></div>');
          }
          if (showEventLocation && info.event.extendedProps.location) {
            texts.push('<div class="pgc-popup-row pgc-event-location"><div class="pgc-popup-row-icon"><span class="dashicons dashicons-location"></span></div><div class="pgc-popup-row-value">' + info.event.extendedProps.location + '</div></div>');
          }
          if (showEventAttendees && info.event.extendedProps.attendees && info.event.extendedProps.attendees.length) {
            texts.push('<div class="pgc-popup-row pgc-event-attendees"><div class="pgc-popup-row-icon"><span class="dashicons dashicons-groups"></span></div><div class="pgc-popup-row-value"><ul>' + info.event.extendedProps.attendees.map(function(attendee) {
              return '<li>' + attendee.email + '</li>';
            }).join('') + '</ul></div></div>');
          }
          if (showEventAttachments && info.event.extendedProps.attachments && info.event.extendedProps.attachments.length) {
            texts.push('<div class="pgc-popup-row pgc-event-attachments"><div class="pgc-popup-row-icon"><span class="dashicons dashicons-paperclip"></span></div><div class="pgc-popup-row-value"><ul>' + info.event.extendedProps.attachments.map(function(attachment) {
              return '<li><a target="__blank" href="' + attachment.fileUrl + '">' + attachment.title + '</a></li>';
            }).join('<br>') + '</ul></div></div>');
          }
          var hasCreator = showEventCreator && info.event.extendedProps.creator && (info.event.extendedProps.creator.email || info.event.extendedProps.creator.displayName);
          if (showEventCalendarname || hasCreator) {
            texts.push('<div class="pgc-popup-row pgc-event-calendarname-creator"><div class="pgc-popup-row-icon"><span class="dashicons dashicons-calendar-alt"></span></div><div class="pgc-popup-row-value">');
            if (showEventCalendarname) {
              texts.push(allCalendars[info.event.extendedProps.calId].summary);
              if (hasCreator) {
                texts.push('<br>');
              }
            }
            if (hasCreator) {
              texts.push('Created by: ' + (info.event.extendedProps.creator.displayName || info.event.extendedProps.creator.email));
            }
            texts.push('</div></div>');
          }
          if (showEventLink) {
            texts.push('<div class="pgc-popup-row pgc-event-link"><div class="pgc-popup-row-icon"><span class="dashicons dashicons-external"></span></div><div class="pgc-popup-row-value"><a target="__blank" href="' + info.event.extendedProps.htmlLink + '">Go to event</a></div></div>');
          }
          info.el.setAttribute("data-tippy-content",  texts.join("\n"));
        }
      },
      events: function(arg, successCcallback, failureCallback) {

        var start = arg.start;
        var end = arg.end;
        var fStart = dateFormat(start);
        var fEnd = dateFormat(end);

        var xhr = new XMLHttpRequest();
        var formData = new FormData();
        formData.append("_ajax_nonce", pgc_object.nonce);
        formData.append("action", "pgc_ajax_get_calendar");
        formData.append("start", fStart);
        formData.append("end", fEnd);
        formData.append("thisCalendarids", thisCalendarids.join(","));
        xhr.onload = function(eLoad) {
          try {
            var response = JSON.parse(this.response);
            if ("error" in response) {
              throw new Error(response);
            }
            var items = [];
            if ("items" in response) {
              // Merge calendar backgroundcolor and items
              var calendars = response.calendars;
              response.items.forEach(function(item) {
                // Check if we have this calendar - if we get cached items, but someone unselected
                // a calendar in the admin, we can get items for unselected calendars.
                if (!(item.calId in calendars)) return;
                if (!getConfigBackgroundColor(config)) {
                  item.backgroundColor = calendars[item.calId].backgroundColor;
                }
                //item.start = new Date(item.start);
                //item.end = new Date(item.end);
                if (item.allDay) {

                }
                items.push(item);
              });
              console.log("items", items);
              currentAllEvents = items;
              handleCalendarFilter(response.calendars);
            }
            //items = getFilteredEvents();
            successCcallback([]);
            setEvents();
          } catch (ex) {
            calendarWrapper.classList.remove("pgc-loading");
            calendarWrapper.classList.add("pgc-loading-error");
            console.error(ex);
            successCcallback([]);
          } finally {
            xhr = null;
          }
        };
        xhr.onerror = function(eError) {
          calendarWrapper.classList.remove("pgc-loading");
          calendarWrapper.classList.add("pgc-loading-error");
          console.error(eError);
          successCcallback([]);
        };
        xhr.open("POST", pgc_object.ajax_url);
        xhr.send(formData);
      }
    });

    if (hidePassed || hideFuture) {
      config.validRange = {};
    }

    if (hidePassed) {
      config.validRange.start = new Date();
    }
    if (hideFuture) {
      config.validRange.end = new Date();
    }

    fullCalendar = new FullCalendar.Calendar($calendar, Object.assign({
      plugins: ['momentTimezone', 'dayGrid', 'list', 'timeGrid'],
      defaultView: 'dayGridMonth',
      nowIndicator: true,
      columnHeader: true,
      columnHeaderFormat: {
        weekday: 'short'
      },
      timeZone: 'America/New_York'
    }, config));
    fullCalendar.render();
    // For debugging, so we have access to it from within the console.
    window.fullCalendars.push(fullCalendar);
  });

  tippy.delegate("body", {
    target: "*[data-tippy-content]",
    allowHTML: true,
    trigger: "click",
    theme: "pgc",
    interactive: true,
    appendTo: document.body,
    theme: 'light-border'
  });

  var startClientX = 0;
  var startClientY = 0;
  var popupElement = null;
  var popupElementStartX = 0;
  var popupElementStartY = 0;

  function onBodyMouseDown(e) {
    
    var el = e.target || e.srcElement;
    
    if (!el.classList.contains('pgc-popup-draghandle')) return;

    while (el && el.className !== 'tippy-popper') {
      el = el.parentNode;
    }
    popupElement = el;
    if (!popupElement) return;
    var transform = popupElement.style.transform.replace("translate3d(", "").replace(")", "").split(",");
    popupElementStartX = parseInt(transform[0].replace(" ", ""), 10);
    popupElementStartY = parseInt(transform[1].replace(" ", ""), 10);
    startClientX = e.clientX;
    startClientY = e.clientY;
    document.body.addEventListener("mousemove", onBodyMouseMove);
    document.body.addEventListener("mouseup", onBodyMouseUp);
  }

  function onBodyMouseMove(e) {
    popupElement.style.transform = "translate3d(" + (popupElementStartX + (e.clientX - startClientX)) + "px, " + (popupElementStartY + (e.clientY - startClientY)) + "px, 0px)";
  }

  function onBodyMouseUp() {
    document.body.removeEventListener("mousemove", onBodyMouseMove);
    document.body.removeEventListener("mouseup", onBodyMouseUp);  
  }

  document.body.addEventListener("mousedown", onBodyMouseDown);

}(this));
