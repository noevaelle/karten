jQuery(function($) {
  // Grund-Variablen
  var $wrap         = $('.karte-wrapper'),
      $inner        = $wrap.find('.karte-inner'),
      $img          = $inner.find('#karte-image'),
      $toggle       = $('#zoom-toggle'),
      regions       = JSON.parse($img.attr('data-regions') || '[]'),
      setUrl        = $img.data('set-location-url'),
      getUrl        = $img.data('get-locations-url'),
      naturalW, naturalH, baseScale, initialH,
      zoom = 1, step = 0.1, maxZoom = 5;

  if (!$img.length) return;

  // Button-Templates decodieren
  function decodeTpl(str) {
    str = str.replace(/\\(["'])/g, '$1');
    return $('<textarea/>').html(str).text();
  }
  var tplBtnHome    = decodeTpl(window.tplBtnHome    || ''),
      tplBtnWork    = decodeTpl(window.tplBtnWork    || ''),
      tplBtnDelHome = decodeTpl(window.tplBtnDelHome || ''),
      tplBtnDelWork = decodeTpl(window.tplBtnDelWork || ''),
      tplLegItem    = decodeTpl($('#tpl_karten_legende_item').text());

  // 1) Init: Bild & Hotspots anlegen
  function init() {
    naturalW  = $img[0].naturalWidth;
    naturalH  = $img[0].naturalHeight;
    baseScale = $wrap.width() / naturalW;
    initialH  = Math.round(naturalH * baseScale);
    $wrap.css({ height: initialH + 'px', overflow: 'hidden' });
    $inner.css({
      width:  naturalW + 'px',
      height: naturalH + 'px',
      transform: 'scale(' + baseScale + ')'
    });
    $img.css({ display: 'block', width: '100%', height: 'auto' });

    regions.forEach(function(r) {
      var cls = 'category-' + r.category.toLowerCase().replace(/[^a-z0-9]+/g, '-');
      $('<div class="karte-hotspot"></div>')
        .css({
          left:   r.x + 'px',
          top:    r.y + 'px',
          width:  r.width + 'px',
          height: r.height + 'px'
        })
        .data({
          id:            r.id,
          marker:        r.marker_title,
          category:      r.category,
          location:      r.location,
          initialUsers:  r.initialUsers || [],
          home:          r.home || [],
          work:          r.work || [],
          capHome:       r.cap_home_max || 0,
          capWork:       r.cap_work_max || 0,
          iconHomeClass: r.icon_home_class,
          iconWorkClass: r.icon_work_class,
          iconCategory:  r.icon_category
        })
        .append(
          $('<div class="karte-marker"></div>')
            .addClass(cls)
            .text(r.marker_title)
        )
        .appendTo($inner);
    });

    bindEvents();
    renderLegend();
  }

  // 2) Legende
  function renderLegend() {
    var $leg = $('#karte-legende').empty(),
        items = regions.map(function(r) {
          return {
            marker:       r.marker_title,
            location:     r.location,
            iconCategory: r.icon_category
          };
        });

    items.sort(function(a, b) {
      return a.marker.localeCompare(b.marker);
    });

    items.forEach(function(it) {
      var html = tplLegItem
                   .replace(/{{iconCategory}}/g, it.iconCategory)
                   .replace(/{{marker}}/g,       it.marker)
                   .replace(/{{location}}/g,     it.location);
      $leg.append(html);
    });
  }

  // 3) Counter-Helfer
  function fmt(used, cap) {
    return cap > 0 ? '[' + used + '/' + cap + ']' : '';
  }

  // 4) Events: Zoom & Popup
  function bindEvents() {
    // Zoom-Toggle
    $toggle.on('click', function() {
      $(this).toggleClass('active');
    });

    // Wheel-Zoom
    $wrap.on('wheel', function(e) {
      if (!$toggle.hasClass('active')) return;
      e.preventDefault();
      zoom = e.originalEvent.deltaY < 0
           ? Math.min(maxZoom, zoom + step)
           : Math.max(1,       zoom - step);
      applyZoom();
    });

    // Hotspot-Klick → Popup
    $wrap.on('click touchend', '.karte-hotspot', function(e) {
      e.stopPropagation();
      $('.karte-popup').remove();

      var $hs          = $(this),
          id           = $hs.data('id'),
          marker       = $hs.data('marker'),
          category     = $hs.data('category'),
          location     = $hs.data('location'),
          initialUsers = $hs.data('initialUsers') || [],
          homeList     = $hs.data('home')         || [],
          workList     = $hs.data('work')         || [],
          capH         = $hs.data('capHome')      || 0,
          capW         = $hs.data('capWork')      || 0,
          iconHomeCls  = $hs.data('iconHomeClass'),
          iconWorkCls  = $hs.data('iconWorkClass'),
          tplRaw       = $('#tpl_karten_popup_wrapper').text() || '',
          tpl          = $('<textarea/>').html(tplRaw).text(),
          off          = $hs.offset(),
          left         = off.left + $hs.outerWidth() / 2 + 5,
          top          = off.top  + $hs.outerHeight() / 2 + 5;

      // Build <li> items
      var listItems = [];
      initialUsers.forEach(function(u) {
        listItems.push(
          '<li><i class="' + iconHomeCls + '"></i> '
          + (u.link ? '<a href="' + u.link + '">' + u.name + '</a>' : u.name)
          + '</li>'
        );
      });
      homeList.forEach(function(u) {
        listItems.push(
          '<li><i class="' + iconHomeCls + '"></i> '
          + '<a href="' + u.link + '">' + u.name + '</a>'
          + '</li>'
        );
      });
      workList.forEach(function(u) {
        listItems.push(
          '<li><i class="' + iconWorkCls + '"></i> '
          + '<a href="' + u.link + '">' + u.name + '</a>'
          + '</li>'
        );
      });
      var usersHtml = listItems.join('');

      // Compute used home count
      var usedHome = initialUsers.length + homeList.length;

      // Buttons
      var btnHome = tplBtnHome.replace(/\{AREAID\}/g, id)
                              .replace(/\{COUNTER\}/g, fmt(usedHome, capH)),
          btnWork = tplBtnWork.replace(/\{AREAID\}/g, id)
                              .replace(/\{COUNTER\}/g, fmt(workList.length, capW)),
          btnDelH = homeList.length
                      ? tplBtnDelHome.replace(/\{AREAID\}/g, id)
                      : '',
          btnDelW = workList.length
                      ? tplBtnDelWork.replace(/\{AREAID\}/g, id)
                      : '';

      // Replace placeholders
      var popupHtml = tpl
        .replace(/\{\{marker_title\}\}/g, marker)
        .replace(/\{\{location\}\}/g,     location)
        .replace(/\{\{category\}\}/g,     category)
        .replace(/\{\{users\}\}/g,        usersHtml)
        .replace(/\{\{categoryclass\}\}/g,
          'categoryclass_' + category.toLowerCase().replace(/[^a-z0-9]+/g, '_')
        );

      // Render Popup
      var $pop = $('<div class="karte-popup"></div>')
        .html(popupHtml)
        .css({ left: left + 'px', top: top + 'px' })
        .appendTo('body');

      // Remove list bullets
      $pop.find('ul').css({ 'list-style': 'none', margin: 0, padding: 0 });

      // Insert buttons inside the wrapper’s .button-block
      var $block = $pop.find('.button-block');
      $block.html(btnHome + btnWork + btnDelH + btnDelW);
    });

    // Click outside → close
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.karte-hotspot, .karte-popup').length) {
        $('.karte-popup').remove();
      }
    });

    // Button-Handler
    $(document).on('click', '.karte-popup .set-home', function(e) {
      e.stopPropagation();
      setLocation($(this).data('areaId'), 'home');
    });
    $(document).on('click', '.karte-popup .set-work', function(e) {
      e.stopPropagation();
      setLocation($(this).data('areaId'), 'work');
    });
    $(document).on('click', '.karte-popup .del-home', function(e) {
      e.stopPropagation();
      setLocation($(this).data('areaId'), 'remove_home');
    });
    $(document).on('click', '.karte-popup .del-work', function(e) {
      e.stopPropagation();
      setLocation($(this).data('areaId'), 'remove_work');
    });
  }

  // 5) setLocation + refresh
  function setLocation(id, type) {
    $.post(setUrl, { area: id, type: type }, function(res) {
      if (res.error) alert(res.error);
      else          refreshRegion(id);
    }, 'json');
  }

  // 6) AJAX-Refresh
  function refreshRegion(id) {
    if (!getUrl) return location.reload();
    $.get(getUrl, { area: id }, function(res) {
      if (res.error) { alert(res.error); return; }
      var reg = regions.find(function(r) { return r.id === id; });
      reg.home         = res.home  || [];
      reg.work         = res.work  || [];
      reg.cap_home_max = res.capHome != null ? res.capHome : reg.cap_home_max;
      reg.cap_work_max = res.capWork != null ? res.capWork : reg.cap_work_max;

      var $hs = $wrap.find('.karte-hotspot').filter(function() {
        return $(this).data('id') === id;
      });
      $hs.data('home',    reg.home)
         .data('work',    reg.work)
         .data('capHome', reg.cap_home_max)
         .data('capWork', reg.cap_work_max);

      if ($('.karte-popup').length) {
        $('.karte-popup').remove();
        $hs.trigger('click');
      }
    }, 'json');
  }

  // 7) Zoom anwenden
  function applyZoom() {
    var scale = baseScale * zoom;
    $inner.css('transform', 'scale(' + scale + ')');
    $wrap.toggleClass('zoomed', zoom > 1);
  }

  // 8) Start
  if ($img[0].complete) init();
  else                  $img.on('load', init);
});
