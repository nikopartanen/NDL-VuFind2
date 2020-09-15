$(document).on('click', '#refresh', function () {
    var $link = $('li.active a[data-toggle="tab"]');
    $link.parent().removeClass('active');
    $link.tab('show');
});

$(document).ready(function() {
    $('a[data-toggle=tab]').each(function () {
        $(this).on('shown.bs.tab', function () {
            $('.result-view-grid .masonry-wrapper').masonry('layout');
        });
    });
})


function goBack() {
   window.history.back();
}
