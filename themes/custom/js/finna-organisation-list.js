/*global finna */
finna.organisationList = (function finnaOrganisationList() {
  function initOrganisationPageLinksForParticipants() {
    var museumIds = [];
    var libraryIds = [];
    var timeout = false;
    $('.page-link').not('.done').map(function setupOrganisationPageLinks() {
      $(this).one('inview', function onInViewLink() {
        var id = $(this).data('organisation');
        var sector = $(this).data('sector');
        if (sector === 'mus') {
          museumIds.push({'id': id, 'sector': sector});
        } else {
          libraryIds.push({'id': id, 'sector': sector});
        }
        getOrganisationLinks();
      });
    });

    function getOrganisationLinks() {
      if (timeout) {
        clearTimeout(timeout);
      }
      timeout = setTimeout(function getLinks() {
        if (libraryIds.length > 0) {
          finna.layout.getOrganisationPageLink(libraryIds, false, false, onGetOrganisationPageLink);
        }
        if (museumIds.length > 0) {
          finna.layout.getOrganisationPageLink(museumIds, false, false, onGetOrganisationPageLink);
        }

        function onGetOrganisationPageLink(response) {
          if (response) {
            $.each(response, function handleLink(id, url) {
              var link = $('.organisations .page-link[data-organisation="' + id + '"]');
              link.wrapInner($('<a/>').attr('href', url));
            });
          }
        }
        libraryIds = [];
        museumIds = [];
        timeout = false;
      }, 500);
    }
  }
  var my = {
    init: function init() {
      initOrganisationPageLinksForParticipants();
    }
  };

  return my;
})();